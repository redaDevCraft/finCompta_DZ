<?php

namespace App\Http\Controllers;

use App\Exports\VatReportExport;
use App\Models\Account;
use App\Models\ExpenseLine;
use App\Models\InvoiceVatBucket;
use App\Services\AgedBalanceService;
use App\Services\BilanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function vat(Request $request): Response
    {
        $data = $this->buildVatReportData($request);

        return Inertia::render('Reports/Vat', $data);
    }

    public function vatExport(Request $request): BinaryFileResponse
    {
        $data = $this->buildVatReportData($request);

        $year = $data['period']['year'];
        $month = $data['period']['month'];

        $suffix = $month
            ? sprintf('%s_%02d', $year, $month)
            : sprintf('%s_Q%s', $year, $data['period']['quarter']);

        return Excel::download(
            new VatReportExport($data),
            "TVA_{$suffix}.xlsx"
        );
    }

    protected function buildVatReportData(Request $request): array
    {
        $companyId = app('currentCompany')->id;
        $year = (int) ($request->input('year', now()->year));
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $quarter = $request->filled('quarter') ? (int) $request->input('quarter') : null;

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
            $month = now()->month;
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

    public function bilan(Request $request, BilanService $service): Response
    {
        $company = app('currentCompany');
        $asOf = $request->input('as_of_date', now()->endOfYear()->toDateString());

        $bilan = $service->compute($company, $asOf);

        return Inertia::render('Reports/Bilan', $bilan);
    }

    public function bilanPdf(Request $request, BilanService $service): HttpResponse
    {
        $company = app('currentCompany');
        $asOf = $request->input('as_of_date', now()->endOfYear()->toDateString());

        $bilan = $service->compute($company, $asOf);

        $pdf = Pdf::loadView('pdf.bilan', ['bilan' => $bilan])
            ->setPaper('a4');

        return $pdf->download("bilan_{$asOf}.pdf");
    }

    public function agedReceivables(Request $request, AgedBalanceService $service): Response
    {
        return $this->agedBalance($request, $service, 'receivable');
    }

    public function agedPayables(Request $request, AgedBalanceService $service): Response
    {
        return $this->agedBalance($request, $service, 'payable');
    }

    protected function agedBalance(
        Request $request,
        AgedBalanceService $service,
        string $side
    ): Response {
        $company = app('currentCompany');
        $asOf = $request->input('as_of_date', now()->toDateString());

        $defaultPrefix = $side === 'receivable' ? '411' : '401';
        $accountCode = $request->input('account_code', $defaultPrefix);

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_lettrable', true)
            ->where(function ($q) use ($accountCode) {
                $q->where('code', $accountCode)
                    ->orWhere('code', 'LIKE', $accountCode.'%');
            })
            ->orderBy('code')
            ->first();

        $lettrableAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_lettrable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label']);

        $report = $account
            ? $service->compute($account, $asOf)
            : ['rows' => [], 'totals' => ['b0_30' => 0, 'b30_60' => 0, 'b60_90' => 0, 'b90_plus' => 0, 'total' => 0]];

        return Inertia::render('Reports/AgedBalance', [
            'side' => $side,
            'as_of_date' => $asOf,
            'account_code' => $accountCode,
            'account' => $account,
            'accounts' => $lettrableAccounts,
            'report' => $report,
        ]);
    }
}
