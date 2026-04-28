<?php

namespace App\Services;

use App\DTOs\ChargilyConfig;
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

    public function __construct(
        private readonly ChargilyPay $client,
        private readonly ChargilyConfig $config
    ){}
    
    public function baseUrl(): string
    {
      return $this->config->baseUrl;
    }

    public function isConfigured(): bool
    {
       return strlen($this->config->secretKey) >= 16 && strlen($this->config->apiKey) >= 16;
    }

    

    /**
     * Create a hosted checkout session.
     *
     * Chargily authenticates with `Authorization: Bearer <secret>` (see API docs). The SDK maps
     * `public` → publishable key (`test_pk_` / `live_pk_`) and `secret` → secret key (`test_sk_` / `live_sk_`).
     *
     * @return array{ok: true, id: string, url: string, raw: mixed}|array{ok: false, error: string, detail?: string}
     */
    public function createCheckout(
        Payment $payment,
        string $successUrl,
        string $failureUrl,
        string $webhookUrl
    ): array {
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
            'locale' => $this->config->locale,
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
            $checkout = $this->client->checkouts()->create($payload);
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
        $secret = $this->config->webhookSecret;

        if ($secret !== '' && (str_starts_with($secret, 'http://') || str_starts_with($secret, 'https://'))) {
            $secret = '';
        }

        if ($secret === '') {
            $secret = $this->config->secretKey;
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
