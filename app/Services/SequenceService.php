<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates document numbers per company / document_type / fiscal_year.
 *
 * Pattern: PREFIX-YEAR-NNNN (e.g. FAC-2026-0001).
 */
final class SequenceService
{
    /**
     * @return array{sequence_id: string, number: string}
     */
    public function nextInvoiceNumber(
        string $companyId,
        string $documentType,
        string $issueDate,
    ): array {
        return DB::transaction(function () use ($companyId, $documentType, $issueDate): array {
            $year = (int) date('Y', strtotime($issueDate));

            $sequence = InvoiceSequence::query()
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->where('fiscal_year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $prefix = match ($documentType) {
                    'invoice' => 'FAC',
                    'credit_note' => 'AV',
                    'quote' => 'DEV',
                    'delivery_note' => 'BL',
                    default => 'DOC',
                };

                $sequence = InvoiceSequence::query()->create([
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'fiscal_year' => $year,
                    'prefix' => $prefix,
                    'last_number' => 0,
                    'total_issued' => 0,
                    'total_voided' => 0,
                    'locked' => false,
                ]);
            }

            $next = $sequence->last_number + 1;
            $number = sprintf('%s-%d-%04d', $sequence->prefix ?? 'DOC', $year, $next);

            $sequence->update([
                'last_number' => $next,
                'total_issued' => $sequence->total_issued + 1,
            ]);

            return [
                'sequence_id' => $sequence->id,
                'number' => $number,
            ];
        });
    }
}

