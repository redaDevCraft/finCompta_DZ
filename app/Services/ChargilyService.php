<?php

namespace App\Services;

use App\Models\Payment;
use Chargily\ChargilyPay\Auth\Credentials;
use Chargily\ChargilyPay\ChargilyPay;
use Chargily\ChargilyPay\Exceptions\InvalidHttpResponse;
use Chargily\ChargilyPay\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * Chargily Pay V2 via official SDK (same stack as chargily/chargily-pay in reference apps).
 *
 * @see https://github.com/Chargily/chargily-pay-php
 */
class ChargilyService
{
    public function baseUrl(): string
    {
        $mode = strtolower((string) config('services.chargily.mode', 'test'));

        return $mode === 'live'
            ? (string) config('services.chargily.base_url_live')
            : (string) config('services.chargily.base_url_test');
    }

    public function isConfigured(): bool
    {
        $public = trim((string) config('services.chargily.api_key', ''));
        $secret = trim((string) config('services.chargily.secret_key', ''));

        return strlen($public) >= 16 && strlen($secret) >= 16;
    }

    protected function client(): ChargilyPay
    {
        $mode = strtolower((string) config('services.chargily.mode', 'test'));
        if (! in_array($mode, ['test', 'live'], true)) {
            $mode = 'test';
        }

        return new ChargilyPay(new Credentials([
            'mode' => $mode,
            'public' => trim((string) config('services.chargily.api_key')),
            'secret' => trim((string) config('services.chargily.secret_key')),
        ]));
    }

    /**
     * Create a hosted checkout session.
     *
     * Chargily authenticates with `Authorization: Bearer <secret>` (see API docs). The SDK maps
     * `public` → publishable key (`test_pk_` / `live_pk_`) and `secret` → secret key (`test_sk_` / `live_sk_`).
     *
     * @return array{ok: true, id: string, url: string, raw: mixed}|array{ok: false, error: string, detail?: string}
     */
    public function createCheckout(Payment $payment, string $successUrl, string $failureUrl, string $webhookUrl): array
    {
        if (! $this->isConfigured()) {
            Log::warning('Chargily not configured: set CHARGILY_API_KEY and CHARGILY_SECRET_KEY (min 16 chars each).');

            return ['ok' => false, 'error' => 'not_configured'];
        }

        $payment->loadMissing('plan');

        $payload = [
            'amount' => (int) $payment->amount_dzd,
            'currency' => strtolower((string) ($payment->currency ?? 'dzd')),
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'webhook_endpoint' => $webhookUrl,
            'pass_fees_to_customer' => false,
            'locale' => substr((string) config('services.chargily.locale', 'fr'), 0, 2),
            'description' => sprintf(
                'FinCompta DZ — %s (%s)',
                $payment->plan?->name ?? 'Abonnement',
                $payment->billing_cycle
            ),
            'metadata' => [
                'payment_id' => $payment->id,
                'company_id' => $payment->company_id,
                'reference' => $payment->reference,
                'plan_id' => $payment->plan_id,
                'billing_cycle' => $payment->billing_cycle,
            ],
        ];

        $method = strtolower((string) $payment->method);
        if (in_array($method, ['edahabia', 'cib'], true)) {
            $payload['payment_method'] = $method;
        }

        try {
            $checkout = $this->client()->checkouts()->create($payload);
        } catch (ValidationException $e) {
            Log::error('Chargily checkout validation failed', ['message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'validation', 'detail' => $e->getMessage()];
        } catch (InvalidHttpResponse $e) {
            Log::error('Chargily checkout HTTP error', ['message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'http', 'detail' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('Chargily checkout failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'exception', 'detail' => $e->getMessage()];
        }

        if (! $checkout || ! $checkout->getUrl()) {
            return ['ok' => false, 'error' => 'empty_response'];
        }

        return [
            'ok' => true,
            'id' => (string) $checkout->getId(),
            'url' => (string) $checkout->getUrl(),
            'raw' => $checkout->toArray(),
        ];
    }

    /**
     * Chargily signs the raw body with HMAC-SHA256 using the API secret (SDK behaviour).
     * If CHARGILY_WEBHOOK_SECRET is set, it is used instead (e.g. dashboard-specific secret).
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = trim((string) config('services.chargily.webhook_secret', ''));
        // Some setups mistakenly put the webhook URL in CHARGILY_WEBHOOK_SECRET; that is not a signing key.
        if ($secret !== '' && (str_starts_with($secret, 'http://') || str_starts_with($secret, 'https://'))) {
            $secret = '';
        }
        if ($secret === '') {
            $secret = trim((string) config('services.chargily.secret_key', ''));
        }

        if ($secret === '') {
            return app()->environment('local');
        }

        if (! $signatureHeader) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
