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

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'document_type' => $invoice->document_type,
            'status' => $invoice->status,
            'issue_date' => optional($invoice->issue_date)->toDateString(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'subtotal_ht' => (float) $invoice->subtotal_ht,
            'total_ttc' => (float) $invoice->total_ttc,
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
