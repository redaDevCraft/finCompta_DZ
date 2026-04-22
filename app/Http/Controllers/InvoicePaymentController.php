<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\InvoicePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice, InvoicePaymentService $paymentService): RedirectResponse
    {
        $this->authorizeInvoice($invoice);
        $this->authorizePaymentMutation($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:120'],
            'bank_transaction_id' => ['nullable', 'uuid', 'exists:bank_transactions,id'],
        ]);

        $amount = (float) $data['amount'];
        abort_unless($paymentService->canApplyAmount($invoice, $amount), 422, 'Montant supérieur au solde restant.');

        InvoicePayment::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'date' => $data['date'],
            'amount' => $amount,
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'bank_transaction_id' => $data['bank_transaction_id'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $paymentService->refreshInvoiceStatus($invoice->fresh());

        return back()->with('success', 'Paiement enregistré.');
    }

    public function update(
        Request $request,
        Invoice $invoice,
        InvoicePayment $payment,
        InvoicePaymentService $paymentService
    ): RedirectResponse {
        $this->authorizeInvoice($invoice);
        $this->authorizePaymentMutation($request);
        $this->authorizePayment($invoice, $payment);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:120'],
            'bank_transaction_id' => ['nullable', 'uuid', 'exists:bank_transactions,id'],
        ]);

        $amount = (float) $data['amount'];
        abort_unless($paymentService->canApplyAmount($invoice, $amount, $payment), 422, 'Montant supérieur au solde restant.');

        $payment->update([
            'date' => $data['date'],
            'amount' => $amount,
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'bank_transaction_id' => $data['bank_transaction_id'] ?? null,
        ]);

        $paymentService->refreshInvoiceStatus($invoice->fresh());

        return back()->with('success', 'Paiement mis à jour.');
    }

    public function destroy(
        Request $request,
        Invoice $invoice,
        InvoicePayment $payment,
        InvoicePaymentService $paymentService
    ): RedirectResponse {
        $this->authorizeInvoice($invoice);
        $this->authorizePaymentDeletion($request);
        $this->authorizePayment($invoice, $payment);

        $payment->delete();
        $paymentService->refreshInvoiceStatus($invoice->fresh());

        return back()->with('success', 'Paiement supprimé.');
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless($invoice->company_id === app('currentCompany')->id, 403, 'Accès non autorisé à cette facture');
    }

    private function authorizePayment(Invoice $invoice, InvoicePayment $payment): void
    {
        abort_unless(
            $payment->invoice_id === $invoice->id && $payment->company_id === $invoice->company_id,
            403,
            'Paiement non autorisé pour cette facture'
        );
    }

    private function authorizePaymentMutation(Request $request): void
    {
        $user = $request->user();
        abort_if(! $user, 403, 'Utilisateur non authentifié');
        abort_unless($user->hasAnyRole(['owner', 'accountant', 'admin']), 403, 'Permission insuffisante.');
    }

    private function authorizePaymentDeletion(Request $request): void
    {
        $user = $request->user();
        abort_if(! $user, 403, 'Utilisateur non authentifié');
        abort_unless($user->hasAnyRole(['owner', 'admin']), 403, 'Suppression réservée au propriétaire.');
    }
}
