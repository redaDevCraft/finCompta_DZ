<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

class InvoicePaymentService
{
    /**
     * @return array{total_paid: float, remaining: float, payment_status: string}
     */
    public function summarize(Invoice $invoice): array
    {
        $totalPaid = (float) $invoice->payments()->sum('amount');
        $total = abs((float) $invoice->total_ttc);
        $remaining = round($total - $totalPaid, 2);

        $paymentStatus = $this->deriveStatus(
            invoiceStatus: $invoice->status,
            total: $total,
            totalPaid: $totalPaid,
            dueDate: $invoice->due_date instanceof CarbonInterface
                ? $invoice->due_date->toDateString()
                : null,
        );

        return [
            'total_paid' => round($totalPaid, 2),
            'remaining' => $remaining,
            'payment_status' => $paymentStatus,
        ];
    }

    public function refreshInvoiceStatus(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['draft', 'voided', 'replaced'], true)) {
            return;
        }

        $summary = $this->summarize($invoice);
        $status = $summary['payment_status'];

        if ($status === 'overdue' || $status === 'unpaid') {
            $status = 'issued';
        }

        $invoice->update([
            'status' => $status,
        ]);
    }

    public function canApplyAmount(Invoice $invoice, float $amount, ?InvoicePayment $existing = null): bool
    {
        $summary = $this->summarize($invoice);
        $current = $existing ? (float) $existing->amount : 0.0;
        $remainingWithRollback = $summary['remaining'] + $current;

        return $amount > 0 && $amount <= $remainingWithRollback + 0.00001;
    }

    public function deriveStatus(string $invoiceStatus, float $total, float $totalPaid, ?string $dueDate): string
    {
        if (in_array($invoiceStatus, ['draft', 'voided', 'replaced'], true)) {
            return $invoiceStatus;
        }

        $remaining = $total - $totalPaid;
        if ($remaining <= 0.00001) {
            return 'paid';
        }

        if ($totalPaid > 0.00001 && $remaining > 0.00001) {
            return 'partially_paid';
        }

        if ($dueDate && Carbon::parse($dueDate)->lt(Carbon::today())) {
            return 'overdue';
        }

        return 'unpaid';
    }
}
