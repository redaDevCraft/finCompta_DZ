<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\TaxRate;
use Illuminate\Support\Collection;


class TaxComputationService
{
    public function computeLine(array $line): array
    {
        $qty         = (float) ($line['quantity']      ?? 0);
        $unitPrice   = (float) ($line['unit_price_ht'] ?? 0);
        $discountPct = (float) ($line['discount_pct']  ?? 0);
        $vatRatePct  = (float) ($line['vat_rate_pct']  ?? 0);

        $lineHt  = round($qty * $unitPrice * (1 - $discountPct / 100), 2);
        $lineVat = round($lineHt * $vatRatePct / 100, 2);
        $lineTtc = round($lineHt + $lineVat, 2);

        return [
            'line_ht'  => $lineHt,
            'line_vat' => $lineVat,
            'line_ttc' => $lineTtc,
        ];
    }

    /**
     * Sum up subtotal_ht, total_vat, total_ttc from computed lines collection.
     */
    public function computeTotals(Collection $computedLines): array
    {
        $subtotalHt = $computedLines->sum(fn($l) => (float) ($l['line_ht']  ?? 0));
        $totalVat   = $computedLines->sum(fn($l) => (float) ($l['line_vat'] ?? 0));
        $totalTtc   = $computedLines->sum(fn($l) => (float) ($l['line_ttc'] ?? 0));

        return [
            'subtotal_ht' => round($subtotalHt, 2),
            'total_vat'   => round($totalVat, 2),
            'total_ttc'   => round($totalTtc, 2),
        ];
    }

    /**
     * Group lines by VAT rate and produce buckets: rate_pct, base_ht, vat_amount.
     */
    public function computeVatBuckets(Collection $computedLines): array
    {
        return $computedLines
            ->groupBy(fn($line) => (string) ((float) ($line['vat_rate_pct'] ?? 0)))
            ->map(function (Collection $group, string $ratePct) {
                $baseHt    = round($group->sum(fn($l) => (float) ($l['line_ht']  ?? 0)), 2);
                $vatAmount = round($group->sum(fn($l) => (float) ($l['line_vat'] ?? 0)), 2);

                return [
                    'rate_pct'   => (float) $ratePct,
                    'base_ht'    => $baseHt,
                    'vat_amount' => $vatAmount,
                ];
            })
            ->values()
            ->toArray();
    }
    public function computeLineVat(float $baseHt, TaxRate $taxRate): array
    {
        $vatRate = $taxRate->rate_percent / 100;
        $vatAmount = round($baseHt * $vatRate, 2);
        $recoverablePct = $taxRate->recoverable_pct / 100;
        $recoverableAmount = round($vatAmount * $recoverablePct, 2);

        return [
            'vat_rate_pct' => $taxRate->rate_percent,
            'vat_amount' => $vatAmount,
            'recoverable_pct' => $taxRate->recoverable_pct,
            'recoverable_amount' => $recoverableAmount,
            'deductible_amount' => $vatAmount - $recoverableAmount,
        ];
    }

    public function computeInvoiceVat(array $lines, Company $company): array
    {
        $vatBuckets = [];
        $totalVat = 0;
        $totalDeductible = 0;

        foreach ($lines as $line) {
            $taxRate = TaxRate::forCompany($company->id)
                ->find($line['tax_rate_id']) ?? $company->defaultVatRate();
            
            $vatData = $this->computeLineVat($line['total_ht'], $taxRate);
            
            $vatBuckets[] = array_merge($vatData, [
                'base_ht' => $line['total_ht'],
                'tax_rate_id' => $taxRate->id,
            ]);

            $totalVat += $vatData['vat_amount'];
            $totalDeductible += $vatData['deductible_amount'];
        }

        return [
            'vat_buckets' => $vatBuckets,
            'total_vat' => round($totalVat, 2),
            'total_deductible' => round($totalDeductible, 2),
        ];
    }

    public function computeVatReport(Company $company, $fromDate, $toDate): array
    {
        // Collect VAT from invoices/expenses in period
        $collectedVat = Invoice::forCompany($company->id)
            ->issued()
            ->whereBetween('issue_date', [$fromDate, $toDate])
            ->with('vatBuckets')
            ->get()
            ->flatMap->vatBuckets
            ->sum('vat_amount');

        $deductibleVat = Expense::where('company_id', $company->id)
            ->confirmed()
            ->whereBetween('expense_date', [$fromDate, $toDate])
            ->sum('total_vat');

        return [
            'collected_vat' => round($collectedVat, 2),
            'deductible_vat' => round($deductibleVat, 2),
            'net_vat_due' => round($collectedVat - $deductibleVat, 2),
            'period' => $fromDate->format('Y-m') . ' to ' . $toDate->format('Y-m'),
        ];
    }
}

