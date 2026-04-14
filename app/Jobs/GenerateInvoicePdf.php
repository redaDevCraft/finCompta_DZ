<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateInvoicePdf implements ShouldQueue
{
    use Queueable;

    public string $queue = 'pdf';

    public function __construct(public string $invoiceId)
    {
    }

    public function handle(PdfService $pdfService): void
    {
        $invoice = Invoice::query()->findOrFail($this->invoiceId);

        if ($invoice->status === 'draft') {
            return;
        }

        $path = $pdfService->generateInvoicePdf($invoice);

        $invoice->update([
            'pdf_path' => $path,
        ]);
    }
}