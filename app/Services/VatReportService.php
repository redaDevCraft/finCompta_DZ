<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenseLine;
use App\Models\InvoiceVatBucket;
use Illuminate\Support\Facades\DB;

/**
 * TVA report aggregates — shared by the on-screen report and async XLSX export.
 *
 * All queries are explicitly scoped by $companyId so jobs can run without
 * a bound currentCompany in the container.
 */
final class VatReportService
{
    /**
     * @return array{
     *     period: array{year: int, month: int|null, quarter: int|null},
     *     sales_vat_buckets: \Illuminate\Support\Collection<int, array{rate_pct: float, base_ht: float, vat_amount: float}>,
     *     purchase_vat: \Illuminate\Support\Collection<int, array{rate_pct: float, base_ht: float, vat_amount: float}>,
     *     totals: array{collected: float, deductible: float, balance: float},
     * }
     */
    public function buildForCompany(string $companyId, int $year, ?int $month, ?int $quarter): array
    {
        // When neither month nor quarter is selected, the UI defaults the
        // filter to the current calendar month (same as the legacy inline
        // controller). `$month` is reassigned so `period.month` matches.
        $salesQuery = InvoiceVatBucket::query()
            ->selectRaw('invoice_vat_buckets.rate_pct as rate_pct, SUM(invoice_vat_buckets.base_ht) as base_ht, SUM(invoice_vat_buckets.vat_amount) as vat_amount')
            ->join('invoices', 'invoices.id', '=', 'invoice_vat_buckets.invoice_id')
            ->where('invoices.company_id', $companyId)
            ->whereIn('invoices.status', ['issued', 'partially_paid', 'paid'])
            ->whereYear('invoices.issue_date', $year);

        $purchaseQuery = ExpenseLine::query()
            ->selectRaw('expense_lines.vat_rate_pct as rate_pct, SUM(expense_lines.amount_ht) as base_ht, SUM(expense_lines.amount_vat) as vat_amount')
            ->join('expenses', 'expenses.id', '=', 'expense_lines.expense_id')
            ->where('expenses.company_id', $companyId)
            ->whereIn('expenses.status', ['confirmed', 'paid'])
            ->whereYear('expenses.expense_date', $year);

        if ($month) {
            $salesQuery->whereMonth('invoices.issue_date', $month);
            $purchaseQuery->whereMonth('expenses.expense_date', $month);
        } elseif ($quarter) {
            $months = match ($quarter) {
                1 => [1, 2, 3],
                2 => [4, 5, 6],
                3 => [7, 8, 9],
                4 => [10, 11, 12],
                default => [now()->month],
            };

            $salesQuery->whereIn(DB::raw('EXTRACT(MONTH FROM invoices.issue_date)'), $months);
            $purchaseQuery->whereIn(DB::raw('EXTRACT(MONTH FROM expenses.expense_date)'), $months);
        } else {
            $month = (int) now()->month;
            $salesQuery->whereMonth('invoices.issue_date', $month);
            $purchaseQuery->whereMonth('expenses.expense_date', $month);
        }

        $salesVatBuckets = $salesQuery
            ->groupBy('invoice_vat_buckets.rate_pct')
            ->orderBy('rate_pct')
            ->get()
            ->map(fn ($row) => [
                'rate_pct' => (float) $row->rate_pct,
                'base_ht' => (float) $row->base_ht,
                'vat_amount' => (float) $row->vat_amount,
            ])
            ->values();

        $purchaseVat = $purchaseQuery
            ->groupBy('expense_lines.vat_rate_pct')
            ->orderBy('rate_pct')
            ->get()
            ->map(fn ($row) => [
                'rate_pct' => (float) $row->rate_pct,
                'base_ht' => (float) $row->base_ht,
                'vat_amount' => (float) $row->vat_amount,
            ])
            ->values();

        $totalCollected = round($salesVatBuckets->sum('vat_amount'), 2);
        $totalDeductible = round($purchaseVat->sum('vat_amount'), 2);
        $balance = round($totalCollected - $totalDeductible, 2);

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
            ],
            'sales_vat_buckets' => $salesVatBuckets,
            'purchase_vat' => $purchaseVat,
            'totals' => [
                'collected' => $totalCollected,
                'deductible' => $totalDeductible,
                'balance' => $balance,
            ],
        ];
    }

    /**
     * Filename suffix for exports, e.g. 2026_03 or 2026_Q2.
     *
     * @param  array{year: int, month: int|null, quarter: int|null}  $period
     */
    public function exportFilenameSuffix(array $period): string
    {
        $year = $period['year'];
        $month = $period['month'] ?? null;
        $quarter = $period['quarter'] ?? null;

        if ($month) {
            return sprintf('%s_%02d', $year, $month);
        }

        if ($quarter) {
            return sprintf('%s_Q%s', $year, $quarter);
        }

        return sprintf('%s_%02d', $year, now()->month);
    }
}
