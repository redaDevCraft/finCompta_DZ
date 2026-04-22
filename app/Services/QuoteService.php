<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    public function __construct(
        protected TaxComputationService $tax,
    ) {}

    public function saveLines(Quote $quote, array $linesData): void
    {
        DB::transaction(function () use ($quote, $linesData) {
            $quote->load('lines');
            $quote->lines()->delete();

            $computedLines = collect($linesData)
                ->values()
                ->map(function (array $line, int $index) {
                    $normalized = [
                        'description' => $line['description'] ?? null,
                        'quantity' => (float) ($line['quantity'] ?? 0),
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'vat_rate' => (float) ($line['vat_rate'] ?? 0),
                        'sort_order' => $line['sort_order'] ?? $index,
                    ];

                    $computed = $this->tax->computeLine([
                        'quantity' => $normalized['quantity'],
                        'unit_price_ht' => $normalized['unit_price'],
                        'discount_pct' => 0,
                        'vat_rate_pct' => $normalized['vat_rate'],
                    ]);

                    return array_merge($normalized, [
                        'line_total' => $computed['line_ttc'],
                        'line_ht' => $computed['line_ht'],
                        'line_vat' => $computed['line_vat'],
                        'line_ttc' => $computed['line_ttc'],
                    ]);
                });

            $quote->lines()->createMany(
                $computedLines
                    ->map(fn (array $line) => [
                        'description' => $line['description'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'vat_rate' => $line['vat_rate'],
                        'line_total' => $line['line_total'],
                        'sort_order' => $line['sort_order'],
                    ])
                    ->toArray()
            );

            $totals = $this->computeTotals($computedLines);

            $quote->fill($totals);
            $quote->save();
        });
    }

    public function computeTotals(Collection $computedLines): array
    {
        $subtotal = $computedLines->sum(fn (array $line) => (float) ($line['line_ht'] ?? 0));
        $taxTotal = $computedLines->sum(fn (array $line) => (float) ($line['line_vat'] ?? 0));
        $total = $computedLines->sum(fn (array $line) => (float) ($line['line_ttc'] ?? 0));

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => 0.0,
            'tax_total' => round($taxTotal, 2),
            'total' => round($total, 2),
        ];
    }
}
