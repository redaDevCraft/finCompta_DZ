<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PdfService
{
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $invoice->load([
            'lines',
            'vatBuckets',
            'contact',
            'company',
        ]);

        if ($invoice->status === 'draft') {
            throw new RuntimeException('Impossible de générer un PDF pour une facture brouillon.');
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ])->setPaper('a4');

        $path = sprintf(
            'invoices/pdf/%s/%s.pdf',
            $invoice->company_id,
            $invoice->invoice_number
        );

        Storage::disk(config('filesystems.default'))->put(
            $path,
            $pdf->output(),
            'private'
        );

        return $path;
    }
}