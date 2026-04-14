<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\OcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentOcr implements ShouldQueue
{
    use Queueable;

    public string $queue = 'ocr';
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $documentId)
    {
    }

    public function handle(OcrService $ocr): void
    {
        try {
            $doc = Document::findOrFail($this->documentId);

            if ($doc->ocr_status !== 'pending') {
                return;
            }

            $doc->update([
                'ocr_status' => 'processing',
            ]);

            try {
                $contents = Storage::get($doc->storage_key);

                if ($contents === null || $contents === '') {
                    throw new \RuntimeException('Document contents are empty or unreadable');
                }

                $text = $ocr->extractText($contents, $doc->mime_type);

                $doc->update([
                    'ocr_raw_text' => $text,
                    'ocr_status' => 'done',
                ]);

                RunAiExtraction::dispatch($this->documentId)->onQueue('ai');
            } catch (\Throwable $e) {
                $doc->update([
                    'ocr_status' => 'failed',
                ]);

                Log::error('OCR failed', [
                    'doc' => $this->documentId,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessDocumentOcr job failed before OCR start', [
                'doc' => $this->documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}