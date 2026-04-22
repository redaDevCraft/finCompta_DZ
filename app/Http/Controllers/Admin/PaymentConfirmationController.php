<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentConfirmationController extends Controller
{
    public function index(): InertiaResponse
    {
        $payments = Payment::query()
            ->with(['company:id,raison_sociale', 'plan:id,name,code'])
            ->whereIn('status', ['pending', 'processing'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function confirm(Payment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        if (! in_array($payment->gateway, ['bon_de_commande', 'chargily'], true)) {
            return back()->withErrors(['payment' => 'Passerelle de paiement non supportée pour la validation.']);
        }

        if (! in_array($payment->status, ['pending', 'processing'], true)) {
            return back()->withErrors(['payment' => 'Ce paiement ne peut plus être confirmé.']);
        }

        if (! $payment->plan || ! $payment->company) {
            return back()->withErrors(['payment' => 'Le plan ou la société liés à ce paiement sont invalides.']);
        }

        if ($payment->gateway === 'bon_de_commande' && empty($payment->proof_upload_path)) {
            return back()->withErrors(['payment' => 'Un justificatif est obligatoire avant confirmation du bon de commande.']);
        }

        $threshold = (int) config('services.saas.manual_double_approval_threshold', 300000);
        $needsSecondApproval = $payment->gateway === 'bon_de_commande' && $payment->amount_dzd >= $threshold;

        if ($needsSecondApproval && $payment->approval_status !== 'awaiting_second_approval') {
            $payment->update([
                'approval_status' => 'awaiting_second_approval',
                'meta' => array_merge($payment->meta ?? [], [
                    'first_approval_by' => request()->user()?->id,
                    'first_approval_at' => now()->toIso8601String(),
                ]),
            ]);

            return redirect()
                ->route('admin.payments.index')
                ->with('success', '1/2 validation enregistrée. Une deuxième validation admin est requise.');
        }

        $firstApproverId = data_get($payment->meta, 'first_approval_by');
        if ($needsSecondApproval && $firstApproverId && (string) $firstApproverId === (string) request()->user()?->id) {
            return back()->withErrors(['payment' => 'La seconde validation doit être faite par un autre admin.']);
        }

        $subscription = $payment->subscription
            ?? $payment->company?->subscription()->first();
        if (
            $subscription &&
            $subscription->next_plan_id &&
            $subscription->pending_change_reason === 'downgrade' &&
            $subscription->next_plan_id !== $payment->plan_id
        ) {
            return back()->withErrors([
                'payment' => 'Ce paiement ne correspond pas au plan de downgrade programmé.',
            ]);
        }

        $subscriptions->markPaymentSucceeded($payment);
        $payment->update([
            'approval_status' => 'approved',
            'admin_confirmed_by' => request()->user()?->id,
            'admin_confirmed_at' => now(),
        ]);

        return redirect()
            ->route('admin.payments.index')
            ->with('success', 'Paiement '.$payment->reference.' marqué comme payé.');
    }

    public function reject(Request $request, Payment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($payment->status === 'paid') {
            return back()->withErrors(['payment' => 'Ce paiement est déjà payé.']);
        }

        if ($payment->status === 'failed') {
            return back()->withErrors(['payment' => 'Ce paiement est déjà rejeté.']);
        }

        $subscriptions->markPaymentFailed($payment, $validated['reason'] ?? 'rejet_admin');
        $payment->update([
            'approval_status' => 'rejected',
            'admin_rejected_by' => $request->user()?->id,
            'admin_rejected_at' => now(),
        ]);

        return redirect()
            ->route('admin.payments.index')
            ->with('success', 'Paiement '.$payment->reference.' rejeté.');
    }

    public function downloadProof(Payment $payment): BinaryFileResponse
    {
        abort_unless($payment->gateway === 'bon_de_commande', 404);
        abort_unless($payment->proof_upload_path && Storage::disk('local')->exists($payment->proof_upload_path), 404);

        $extension = pathinfo($payment->proof_upload_path, PATHINFO_EXTENSION) ?: 'bin';

        return response()->download(
            Storage::disk('local')->path($payment->proof_upload_path),
            'justificatif-'.$payment->reference.'.'.$extension
        );
    }
}
