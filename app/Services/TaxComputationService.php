<?php

namespace App\Services;

use Illuminate\Support\Collection;

class TaxComputationService
{
    public function computeLine(array $line): array
    {
        $quantity = (float) ($line['quantity'] ?? 0);
        $unitPriceHt = (float) ($line['unit_price_ht'] ?? 0);
        $discountPct = (float) ($line['discount_pct'] ?? 0);
        $vatRatePct = (float) ($line['vat_rate_pct'] ?? 0);

        $lineHt = round(
            $quantity * $unitPriceHt * (1 - $discountPct / 100),
            2
        );

        $lineVat = round($lineHt * $vatRatePct / 100, 2);
        $lineTtc = round($lineHt + $lineVat, 2);

        return [
            'line_ht' => $lineHt,
            'line_vat' => $lineVat,
            'line_ttc' => $lineTtc,
        ];
    }

    public function computeTotals(Collection $lines): array
    {
        $subtotalHt = round((float) $lines->sum('line_ht'), 2);
        $totalVat = round((float) $lines->sum('line_vat'), 2);
        $totalTtc = round($subtotalHt + $totalVat, 2);

        return [
            'subtotal_ht' => $subtotalHt,
            'total_vat' => $totalVat,
            'total_ttc' => $totalTtc,
        ];
    }

    public function computeVatBuckets(Collection $lines): array
    {
        return $lines->groupBy('vat_rate_pct')
            ->map(fn ($group, $rate) => [
                'rate_pct' => (float) $rate,
                'base_ht' => round((float) $group->sum('line_ht'), 2),
                'vat_amount' => round((float) $group->sum('line_vat'), 2),
            ])
            ->values()
            ->toArray();
    }
}