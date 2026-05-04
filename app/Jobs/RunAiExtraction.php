<?php

namespace App\Jobs;

use App\Models\AiSuggestion;
use App\Models\Document;
use App\Services\AiExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunAiExtraction implements ShouldQueue
{
    use Queueable;

    public string $queue = 'ai';
    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public readonly string $documentId)
    {
    }

    public function handle(AiExtractionService $ai): void
    {
        try {
            $doc = Document::findOrFail($this->documentId);

            if (empty($doc->ocr_raw_text)) {
                Log::warning('AI extraction skipped: OCR text empty', [
                    'doc' => $this->documentId,
                ]);

                return;
            }

            // ── STEP 1: Classify document type (only if user hasn't pre-selected one) ──
            if (empty($doc->document_type) || $doc->document_type === 'auto') {
                $classification = $ai->classifyDocument($doc->ocr_raw_text);

                $doc->update([
                    'ocr_parsed_hints' => array_merge(
                        $doc->ocr_parsed_hints ?? [],
                        [
                            'detected_type'       => $classification['document_type'],
                            'detected_confidence' => $classification['confidence'],
                            'detected_signals'    => $classification['signals'],
                        ]
                    ),
                ]);
            }

            // ── STEP 2: Extract fields ──
            $result = $ai->extractExpenseFields($doc->ocr_raw_text, $doc->company_id);

            if (! ($result['success'] ?? false)) {
                Log::warning('AI extraction returned no usable result', [
                    'doc'    => $this->documentId,
                    'result' => $result,
                ]);

                return;
            }

            foreach (($result['suggestions'] ?? []) as $suggestion) {
                AiSuggestion::create([
                    'company_id'      => $doc->company_id,
                    'user_id'         => $doc->uploaded_by,
                    'source_type'     => 'document',
                    'source_id'       => $doc->id,
                    'field_name'      => $suggestion['field_name'] ?? null,
                    'suggested_value' => $suggestion['suggested_value'] ?? null,
                    'confidence'      => $suggestion['confidence'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('RunAiExtraction failed', [
                'doc'   => $this->documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}