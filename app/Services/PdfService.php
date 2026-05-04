<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use RuntimeException;

class PdfService
{
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $invoice->load(['lines', 'vatBuckets', 'contact', 'company']);

        if ($invoice->status === 'draft') {
            throw new RuntimeException('Impossible de générer un PDF pour une facture brouillon.');
        }

        // Render Blade to raw HTML string
        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        // Configure mPDF with Arabic support
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs      = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font'      => 'Cairo',   // Arabic-capable font
            'autoScriptToLang'  => true,       // Auto-detect Arabic script
            'autoLangToFont'    => true,       // Apply correct font per script
            'fontDir'           => array_merge($fontDirs, [
                storage_path('fonts'),         // your custom font folder
            ]),
            'fontdata'          => $fontData + [
                'cairo' => [
                    'R'  => 'Cairo-Regular.ttf',
                    'B'  => 'Cairo-Bold.ttf',
                    'useOTL'    => 0xFF,        // ← THIS is what enables Arabic shaping
                    'useKashida' => 75,
                ],
            ],
            'tempDir'           => storage_path('mpdf_tmp'),
        ]);

        $mpdf->autoScriptToLang  = true;
        $mpdf->autoLangToFont    = true;
        $mpdf->WriteHTML($html);

        $path = sprintf(
            'invoices/pdf/%s/%s.pdf',
            $invoice->company_id,
            $invoice->invoice_number
        );

        Storage::disk(config('filesystems.default'))->put(
            $path,
            $mpdf->Output('', 'S'), // 'S' = return as string
            'private'
        );

        return $path;
    }
}