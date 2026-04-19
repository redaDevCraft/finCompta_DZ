<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\OcrHeuristicParser;
use App\Services\OcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the local OCR pipeline on an uploaded document.
 *
 * Responsibilities:
 *  - Transition ocr_status: pending -> processing -> done|failed
 *  - Store the raw extracted text
 *  - Store regex-based hints (deterministic, no AI)
 *  - Persist a human-readable error message on failure
 */
class ProcessDocumentOcr implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(public readonly string $documentId)
    {
        $this->onQueue((string) config('ocr.processing.queue', 'ocr'));
        $this->tries = (int) config('ocr.processing.tries', 2);
        $this->timeout = (int) config('ocr.processing.job_timeout', 180);
    }

    public function handle(OcrService $ocr, OcrHeuristicParser $parser): void
    {
        $doc = Document::withoutGlobalScope('company')->find($this->documentId);

        if (! $doc) {
            Log::warning('OCR job received unknown document id', [
                'doc' => $this->documentId,
            ]);

            return;
        }

        if ($doc->ocr_status !== 'pending') {
            return;
        }

        $doc->update(['ocr_status' => 'processing']);

        try {
            $contents = Storage::disk('local')->get($doc->storage_key);

            if ($contents === null || $contents === '') {
                throw new \RuntimeException('Le fichier stocké est vide ou illisible.');
            }

            $text = $ocr->extractText($contents, $doc->mime_type);

            $doc->update([
                'ocr_raw_text' => $text,
                'ocr_parsed_hints' => $parser->parse($text),
                'ocr_status' => $text === '' ? 'failed' : 'done',
                'ocr_error' => $text === ''
                    ? 'Aucun texte n\'a pu être extrait du document.'
                    : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('OCR pipeline failed', [
                'doc' => $this->documentId,
                'error' => $e->getMessage(),
            ]);

            $doc->update([
                'ocr_status' => 'failed',
                'ocr_error' => $this->humanizeError($e->getMessage()),
            ]);
        }
    }

    private function humanizeError(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return 'Échec de l\'OCR (erreur inconnue).';
        }

        if (mb_strlen($raw) > 500) {
            return mb_substr($raw, 0, 500).'…';
        }

        return $raw;
    }
}
