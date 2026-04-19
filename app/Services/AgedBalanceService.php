<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Groups unlettered (open) lines of a tiers account by contact and by age bucket:
 *   0-30 days, 30-60 days, 60-90 days, over 90 days (based on entry_date vs as-of date).
 *
 * Balance per row = sum(debit) - sum(credit). Positive values are the "asset" view,
 * which is natural for receivables (411). For payables (401) the UI is expected to
 * flip the sign.
 */
class AgedBalanceService
{
    public function compute(Account $account, string|Carbon $asOfDate): array
    {
        $asOf = Carbon::parse($asOfDate)->startOfDay();

        /** @var Collection<int, JournalLine> $lines */
        $lines = JournalLine::query()
            ->with([
                'contact:id,display_name,type',
                'journalEntry:id,entry_date,journal_code,reference,status,company_id',
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $account->company_id)
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->where('journal_lines.account_id', $account->id)
            ->whereNull('journal_lines.lettering_id')
            ->orderBy('journal_entries.entry_date')
            ->select('journal_lines.*')
            ->get();

        $groups = $lines->groupBy(fn ($l) => $l->contact_id ?? '_no_contact');

        $rows = $groups->map(function (Collection $group) use ($asOf) {
            $first = $group->first();
            $contact = $first?->contact;

            $buckets = ['b0_30' => 0.0, 'b30_60' => 0.0, 'b60_90' => 0.0, 'b90_plus' => 0.0];
            $detail = [];

            foreach ($group as $l) {
                $entryDate = Carbon::parse($l->journalEntry->entry_date);
                $days = (int) $entryDate->diffInDays($asOf, false);
                $signed = round((float) $l->debit - (float) $l->credit, 2);

                $bucket = match (true) {
                    $days <= 30 => 'b0_30',
                    $days <= 60 => 'b30_60',
                    $days <= 90 => 'b60_90',
                    default => 'b90_plus',
                };

                $buckets[$bucket] += $signed;

                $detail[] = [
                    'id' => $l->id,
                    'entry_date' => $entryDate->toDateString(),
                    'journal_code' => $l->journalEntry->journal_code,
                    'reference' => $l->journalEntry->reference,
                    'description' => $l->description,
                    'amount' => $signed,
                    'age_days' => $days,
                ];
            }

            $total = round(array_sum($buckets), 2);

            return [
                'contact_id' => $contact?->id,
                'contact_name' => $contact?->display_name ?? '— Sans tiers —',
                'contact_type' => $contact?->type,
                'b0_30' => round($buckets['b0_30'], 2),
                'b30_60' => round($buckets['b30_60'], 2),
                'b60_90' => round($buckets['b60_90'], 2),
                'b90_plus' => round($buckets['b90_plus'], 2),
                'total' => $total,
                'lines' => $detail,
            ];
        })
            ->filter(fn ($r) => abs($r['total']) > 0.01)
            ->sortByDesc('total')
            ->values();

        $totals = [
            'b0_30' => round($rows->sum('b0_30'), 2),
            'b30_60' => round($rows->sum('b30_60'), 2),
            'b60_90' => round($rows->sum('b60_90'), 2),
            'b90_plus' => round($rows->sum('b90_plus'), 2),
            'total' => round($rows->sum('total'), 2),
        ];

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
