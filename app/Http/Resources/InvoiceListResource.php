<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight shape used by the invoices index table.
 *
 * The index page renders only: number, type, contact name, dates, subtotal,
 * total, status, and pdf presence. We ship only those, not the full model and
 * certainly not the invoice lines. Keeps payload small and indexable.
 *
 * @property-read Invoice $resource
 */
final class InvoiceListResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;
        $total = (float) $invoice->total_ttc;
        $totalPaid = (float) ($invoice->total_paid_amount ?? 0);
        $remaining = round($total - $totalPaid, 2);

        $paymentStatus = $invoice->status;
        if (! in_array($invoice->status, ['draft', 'voided', 'replaced', 'paid'], true)) {
            if ($remaining <= 0.00001) {
                $paymentStatus = 'paid';
            } elseif ($totalPaid > 0.00001) {
                $paymentStatus = 'partially_paid';
            } elseif ($invoice->due_date && $invoice->due_date->isPast()) {
                $paymentStatus = 'overdue';
            } else {
                $paymentStatus = 'unpaid';
            }
        }

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'document_type' => $invoice->document_type,
            'status' => $invoice->status,
            'issue_date' => optional($invoice->issue_date)->toDateString(),
            'created_at' => optional($invoice->created_at)->toDateTimeString(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'subtotal_ht' => (float) $invoice->subtotal_ht,
            'total_ttc' => (float) $invoice->total_ttc,
            'total_paid' => $totalPaid,
            'remaining' => $remaining,
            'payment_status' => $paymentStatus,
            'currency' => $invoice->currency,
            'has_pdf' => ! empty($invoice->pdf_path),
            'contact' => $invoice->relationLoaded('contact') && $invoice->contact
                ? [
                    'id' => $invoice->contact->id,
                    'display_name' => $invoice->contact->display_name,
                ]
                : null,
        ];
    }
}
