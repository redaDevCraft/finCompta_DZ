<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Plan;
use App\Models\RefundRequest;
use App\Services\ChargilyService;
use App\Services\SubscriptionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BillingController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $company = app('currentCompany');
        $subscription = $company->subscription()->with('plan')->first();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $payments = Payment::query()
            ->where('company_id', $company->id)
            ->with('plan:id,name,code')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();
        $refundRequests = RefundRequest::query()
            ->where('company_id', $company->id)
            ->with('payment:id,reference')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return Inertia::render('Billing/Index', [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'trial_ends_at' => optional($subscription->trial_ends_at)?->toIso8601String(),
                'current_period_ends_at' => optional($subscription->current_period_ends_at)?->toIso8601String(),
                'grace_ends_at' => optional($subscription->grace_ends_at)?->toIso8601String(),
                'days_remaining' => $subscription->daysRemaining(),
                'is_on_trial' => $subscription->isOnTrial(),
                'is_active' => $subscription->isActive(),
                'last_payment_method' => $subscription->last_payment_method,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'code' => $subscription->plan->code,
                    'name' => $subscription->plan->name,
                ] : null,
            ] : null,
            'plans' => $plans,
            'payments' => $payments,
            'refund_requests' => $refundRequests,
            'chargily_ready' => app(ChargilyService::class)->isConfigured(),
        ]);
    }

    /**
     * Display the checkout selector for a given plan+cycle.
     * User picks Edahabia / CIB (Chargily) or Bon de commande.
     */
    public function checkout(Request $request, SubscriptionService $subscriptions): InertiaResponse|RedirectResponse
    {
        $planCode = (string) $request->query('plan');
        $cycle = in_array($request->query('cycle'), ['monthly', 'yearly'], true)
            ? $request->query('cycle')
            : 'monthly';

        $plan = Plan::query()->where('code', $planCode)->where('is_active', true)->first();

        if (! $plan) {
            return redirect()->route('billing.index')
                ->withErrors(['plan' => 'Plan introuvable.']);
        }

        $company = app('currentCompany');
        $subscription = $company->subscription()->first()
            ?? $subscriptions->startTrialForCompany($company, $plan);

        return Inertia::render('Billing/Checkout', [
            'plan' => $plan,
            'cycle' => $cycle,
            'amount_dzd' => $plan->priceForCycle($cycle),
            'subscription' => $subscription,
            'chargily_ready' => app(ChargilyService::class)->isConfigured(),
        ]);
    }

    /**
     * Create a Chargily hosted-checkout session, then redirect externally.
     */
    public function startChargily(Request $request, ChargilyService $chargily): RedirectResponse
    {
        $validated = $request->validate([
            'plan_code' => ['required', 'exists:plans,code'],
            'cycle' => ['required', 'in:monthly,yearly'],
            'method' => ['required', 'in:edahabia,cib'],
        ]);

        $company = app('currentCompany');
        $plan = Plan::query()->where('code', $validated['plan_code'])->firstOrFail();
        $subscription = $company->subscription()->first();

        $payment = Payment::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription?->id,
            'plan_id' => $plan->id,
            'gateway' => 'chargily',
            'method' => $validated['method'],
            'billing_cycle' => $validated['cycle'],
            'amount_dzd' => $plan->priceForCycle($validated['cycle']),
            'currency' => 'DZD',
            'status' => 'pending',
            'approval_status' => 'none',
        ]);

        $result = $chargily->createCheckout(
            payment: $payment,
            successUrl: route('billing.success', ['payment' => $payment->id], true),
            failureUrl: route('billing.failure', ['payment' => $payment->id], true),
            webhookUrl: $this->chargilyWebhookEndpointForCheckout(),
        );

        if (! ($result['ok'] ?? false) || empty($result['url'])) {
            $payment->update([
                'status' => 'failed',
                'meta' => array_merge($payment->meta ?? [], [
                    'error' => 'checkout_creation_failed',
                    'chargily_error' => $result['error'] ?? 'unknown',
                    'chargily_detail' => $result['detail'] ?? null,
                ]),
            ]);

            return redirect()->route('billing.checkout', [
                'plan' => $plan->code, 'cycle' => $validated['cycle'],
            ])->withErrors(['chargily' => $this->chargilyCheckoutUserMessage($result)]);
        }

        $payment->update([
            'checkout_id' => $result['id'],
            'checkout_url' => $result['url'],
            'status' => 'processing',
            'meta' => array_merge($payment->meta ?? [], ['chargily_raw' => $result['raw']]),
        ]);

        $hop = route('billing.chargily.redirect', $payment, true);

        // Full document navigation (native form POST) — browser follows 302 → hop → 302 to Chargily.
        return redirect()->to($hop);
    }

    /**
     * Full-page bridge to Chargily hosted checkout (after POST created checkout_url).
     */
    public function redirectToChargily(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->company_id === app('currentCompany')->id, 403);
        abort_unless($payment->gateway === 'chargily', 404);
        abort_if(empty($payment->checkout_url), 404);

        return redirect()->away($payment->checkout_url);
    }

    public function success(Request $request, Payment $payment): InertiaResponse
    {
        abort_unless($payment->company_id === app('currentCompany')->id, 404);

        return Inertia::render('Billing/Success', [
            'payment' => $payment->load('plan'),
        ]);
    }

    public function failure(Request $request, Payment $payment): InertiaResponse
    {
        abort_unless($payment->company_id === app('currentCompany')->id, 404);

        if ($payment->status !== 'paid') {
            $payment->update(['status' => 'failed']);
        }

        return Inertia::render('Billing/Failure', [
            'payment' => $payment->load('plan'),
        ]);
    }

    /**
     * Chargily webhook (public endpoint, HMAC-verified).
     */
    public function webhookChargily(Request $request, ChargilyService $chargily, SubscriptionService $subscriptions): LaravelResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('signature');
        $data = json_decode($raw, true) ?: [];
        $event = $data['type'] ?? $data['event'] ?? null;
        $entity = $data['data'] ?? [];

        $checkoutId = $entity['id'] ?? null;
        $metadata = $entity['metadata'] ?? [];
        $paymentId = $metadata['payment_id'] ?? null;
        $eventId = is_string($entity['id'] ?? null) ? (string) $entity['id'] : null;

        $payment = null;
        if ($paymentId) {
            $payment = Payment::query()->find($paymentId);
        } elseif ($checkoutId) {
            $payment = Payment::query()->where('checkout_id', $checkoutId)->first();
        }

        $signatureValid = $chargily->verifyWebhookSignature($raw, $signature);
        $isDuplicate = $eventId
            ? PaymentWebhookLog::query()
                ->where('gateway', 'chargily')
                ->where('event_id', $eventId)
                ->exists()
            : false;

        PaymentWebhookLog::query()->create([
            'gateway' => 'chargily',
            'event_id' => $eventId,
            'event_name' => is_string($event) ? $event : null,
            'signature_header' => is_string($signature) ? $signature : null,
            'payment_id' => $payment?->id,
            'signature_valid' => $signatureValid,
            'is_duplicate' => $isDuplicate,
            'payload' => $data,
            'received_at' => now(),
        ]);

        if (! $signatureValid) {
            return response('invalid signature', 403);
        }

        if (! $payment) {
            return response('payment not found', 404);
        }

        if ($isDuplicate) {
            return response('duplicate ignored', 200);
        }

        if (! $this->isWebhookModeSafe($entity)) {
            Log::warning('Chargily webhook rejected because mode mismatch.', [
                'event' => $event,
                'configured_mode' => config('services.chargily.mode'),
                'entity_id' => $eventId,
            ]);

            return response('mode mismatch', 422);
        }

        if (! $this->isWebhookAmountValid($payment, $entity)) {
            Log::warning('Chargily webhook rejected because amount or currency mismatch.', [
                'payment_id' => $payment->id,
                'payment_reference' => $payment->reference,
                'expected_amount' => $payment->amount_dzd,
                'expected_currency' => strtoupper((string) $payment->currency),
                'received_amount' => $entity['amount'] ?? null,
                'received_currency' => strtoupper((string) ($entity['currency'] ?? '')),
            ]);

            return response('amount mismatch', 422);
        }

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], [
                'last_webhook_event' => $event,
                'last_webhook_at' => now()->toIso8601String(),
                'last_webhook_raw' => $entity,
            ]),
        ]);

        if (in_array($event, ['checkout.paid', 'checkout.succeeded', 'payment.succeeded'], true)) {
            $subscriptions->markPaymentSucceeded($payment);
        } elseif (in_array($event, ['checkout.failed', 'checkout.canceled', 'payment.failed'], true)) {
            $subscriptions->markPaymentFailed($payment, $entity['failure_reason'] ?? $event);
        }

        return response('ok', 200);
    }

    /**
     * Generate a "Bon de commande" PDF that the user sends to the auto-entrepreneur.
     * Payment stays in "pending" until admin marks it as paid (or user uploads proof).
     */
    public function startBonDeCommande(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_code' => ['required', 'exists:plans,code'],
            'cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $company = app('currentCompany');
        $plan = Plan::query()->where('code', $validated['plan_code'])->firstOrFail();
        $subscription = $company->subscription()->first();

        $payment = Payment::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription?->id,
            'plan_id' => $plan->id,
            'gateway' => 'bon_de_commande',
            'method' => 'bank_transfer',
            'billing_cycle' => $validated['cycle'],
            'amount_dzd' => $plan->priceForCycle($validated['cycle']),
            'currency' => 'DZD',
            'status' => 'pending',
            'approval_status' => 'proof_missing',
        ]);

        $pdf = Pdf::loadView('pdf.bon_de_commande', [
            'payment' => $payment->fresh(['plan', 'company']),
            'company' => $company,
            'plan' => $plan,
            'cycle' => $validated['cycle'],
            'amount' => $payment->amount_dzd,
            'payee' => config('services.saas.payee'),
            'admin_email' => config('services.saas.admin_email'),
            'generated_at' => now(),
        ]);

        $filename = sprintf('bon-de-commande/%s/%s.pdf', $company->id, $payment->reference);
        Storage::disk('local')->put($filename, $pdf->output());

        $payment->update(['bon_pdf_path' => $filename]);

        return redirect()->route('billing.bon.show', $payment);
    }

    public function showBonDeCommande(Request $request, Payment $payment): InertiaResponse
    {
        abort_unless($payment->company_id === app('currentCompany')->id, 404);
        abort_unless($payment->gateway === 'bon_de_commande', 404);

        return Inertia::render('Billing/BonDeCommande', [
            'payment' => $payment->load('plan'),
            'payee' => config('services.saas.payee'),
            'admin_email' => config('services.saas.admin_email'),
        ]);
    }

    public function downloadBonDeCommande(Request $request, Payment $payment): BinaryFileResponse
    {
        abort_unless($payment->company_id === app('currentCompany')->id, 404);
        abort_unless($payment->bon_pdf_path && Storage::disk('local')->exists($payment->bon_pdf_path), 404);

        return response()->download(
            Storage::disk('local')->path($payment->bon_pdf_path),
            'bon-de-commande-'.$payment->reference.'.pdf'
        );
    }

    public function uploadBonProof(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->company_id === app('currentCompany')->id, 404);
        abort_unless($payment->gateway === 'bon_de_commande', 404);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('proof');
        $detectedMime = (string) $file->getMimeType();
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (! in_array($detectedMime, $allowedMimes, true)) {
            return back()->withErrors(['proof' => 'Type de fichier non supporte.']);
        }
        $path = $file->storeAs(
            'bon-de-commande/'.$payment->company_id.'/proofs',
            $payment->reference.'-'.Str::random(6).'.'.$file->getClientOriginalExtension(),
            'local'
        );

        $hash = hash_file('sha256', $file->getRealPath());

        $payment->update([
            'proof_upload_path' => $path,
            'status' => 'processing',
            'approval_status' => 'proof_uploaded',
            'proof_uploaded_by' => $request->user()?->id,
            'proof_mime' => $detectedMime,
            'proof_size_bytes' => $file->getSize(),
            'proof_sha256' => $hash ?: null,
            'meta' => array_merge($payment->meta ?? [], [
                'proof_uploaded_at' => now()->toIso8601String(),
            ]),
        ]);

        return back()->with('success', 'Justificatif envoyé — nous validons votre paiement sous 24h.');
    }

    /**
     * URL sent to Chargily as webhook_endpoint (must pass PHP filter_var URL validation — use a public https URL, e.g. ngrok).
     * Prefer CHARGILY_WEBHOOK_URL. If CHARGILY_WEBHOOK_SECRET was mistakenly set to a URL, it is used here only (signing still uses API secret).
     */
    private function chargilyWebhookEndpointForCheckout(): string
    {
        $fromConfig = trim((string) config('services.chargily.webhook_url', ''));
        if ($fromConfig !== '') {
            return rtrim($fromConfig, '/');
        }

        $misplaced = trim((string) config('services.chargily.webhook_secret', ''));
        if ($misplaced !== '' && (str_starts_with($misplaced, 'http://') || str_starts_with($misplaced, 'https://'))) {
            return rtrim($misplaced, '/');
        }

        return route('billing.webhook.chargily', [], true);
    }

    /**
     * Human-readable Chargily failure (see https://dev.chargily.com/pay-v2/api-reference/authentication.md — Bearer is the secret key).
     *
     * @param  array<string, mixed>  $result
     */
    private function chargilyCheckoutUserMessage(array $result): string
    {
        $error = (string) ($result['error'] ?? 'unknown');
        $detail = isset($result['detail']) && is_string($result['detail']) ? $result['detail'] : null;

        $messages = [
            'not_configured' => 'Chargily n’est pas configuré : renseignez CHARGILY_API_KEY et CHARGILY_SECRET_KEY (au moins 16 caractères chacune), sans espaces en trop.',
            'validation' => 'Chargily a rejeté les données du checkout (URLs, montant ou devise). Vérifiez APP_URL et les routes success / échec / webhook.',
            'http' => 'Chargily a refusé la requête. Vérifiez que CHARGILY_MODE (test ou live) correspond au mode des clés dans le tableau de bord, que CHARGILY_SECRET_KEY est la clé secrète (test_sk_… ou live_sk_…) et CHARGILY_API_KEY la clé publique (test_pk_… ou live_pk_…). L’API utilise la clé secrète dans l’en-tête Authorization.',
            'empty_response' => 'Chargily n’a pas renvoyé de lien de paiement.',
            'exception' => 'Erreur technique lors de l’appel à Chargily (vérifiez que le paquet PHP chargily/chargily-pay est installé : composer install).',
        ];

        $base = $messages[$error] ?? $messages['exception'];

        if (config('app.debug') && $detail !== null && $detail !== '') {
            return $base.' Détails : '.$detail;
        }

        return $base;
    }

    /**
     * Chargily entities typically expose either live_mode/livemode/mode.
     * We reject test payloads when app is configured as live.
     *
     * @param  array<string, mixed>  $entity
     */
    private function isWebhookModeSafe(array $entity): bool
    {
        $configuredMode = strtolower((string) config('services.chargily.mode', 'test'));
        $liveFlags = [
            $entity['livemode'] ?? null,
            $entity['live_mode'] ?? null,
            isset($entity['mode']) ? strtolower((string) $entity['mode']) === 'live' : null,
        ];

        if ($configuredMode !== 'live') {
            return true;
        }

        foreach ($liveFlags as $flag) {
            if ($flag === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function isWebhookAmountValid(Payment $payment, array $entity): bool
    {
        $amount = $entity['amount'] ?? null;
        $currency = strtoupper((string) ($entity['currency'] ?? ''));

        if (! is_numeric($amount)) {
            return false;
        }

        $expectedCurrency = strtoupper((string) $payment->currency);

        return (int) $amount === (int) $payment->amount_dzd
            && $currency === $expectedCurrency;
    }
}
