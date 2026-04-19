<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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
        if (! in_array($payment->status, ['pending', 'processing'], true)) {
            return back()->withErrors(['payment' => 'Ce paiement ne peut plus être confirmé.']);
        }

        $subscriptions->markPaymentSucceeded($payment);

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

        $subscriptions->markPaymentFailed($payment, $validated['reason'] ?? 'rejet_admin');

        return redirect()
            ->route('admin.payments.index')
            ->with('success', 'Paiement '.$payment->reference.' rejeté.');
    }
}
