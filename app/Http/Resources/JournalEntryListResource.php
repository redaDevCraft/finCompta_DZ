<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight shape used by the journal (ledger) index table.
 *
 * The index only renders one row per entry with aggregate totals and a period
 * label. Full line breakdowns are shown on the entry detail page, not here.
 *
 * The caller must provide:
 *   - `period` relationship loaded with selected columns
 *   - `withSum('lines as total_debit', 'debit')`
 *   - `withSum('lines as total_credit', 'credit')`
 *
 * @property-read JournalEntry $resource
 */
final class JournalEntryListResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        /** @var JournalEntry $entry */
        $entry = $this->resource;

        $totalDebit = (float) ($entry->total_debit ?? 0);
        $totalCredit = (float) ($entry->total_credit ?? 0);

        return [
            'id' => $entry->id,
            'entry_date' => optional($entry->entry_date)->toDateString(),
            'journal_code' => $entry->journal_code,
            'reference' => $entry->reference,
            'description' => $entry->description,
            'status' => $entry->status,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'posted_at' => optional($entry->posted_at)?->toDateTimeString(),
            'period' => $entry->relationLoaded('period') && $entry->period
                ? [
                    'id' => $entry->period->id,
                    'year' => $entry->period->year,
                    'month' => $entry->period->month,
                    'status' => $entry->period->status,
                ]
                : null,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'balanced' => abs($totalDebit - $totalCredit) < 0.01,
            ],
        ];
    }
}
