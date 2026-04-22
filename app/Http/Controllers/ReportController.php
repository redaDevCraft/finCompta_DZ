<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ReportRun;
use App\Services\AgedBalanceService;
use App\Services\AnalyticReportService;
use App\Services\BilanService;
use App\Services\Reports\ReportRunService;
use App\Services\VatReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function vat(Request $request, VatReportService $vatReportService): Response
    {
        $companyId = app('currentCompany')->id;
        $year = (int) ($request->input('year', now()->year));
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $quarter = $request->filled('quarter') ? (int) $request->input('quarter') : null;

        $data = $vatReportService->buildForCompany($companyId, $year, $month, $quarter);

        return Inertia::render('Reports/Vat', $data);
    }

    /**
     * Queue TVA XLSX export — same pattern as bilan PDF (Phase 6).
     */
    public function queueVatExport(Request $request, ReportRunService $runs): RedirectResponse
    {
        $company = app('currentCompany');
        $year = (int) $request->input('year', now()->year);
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $quarter = $request->filled('quarter') ? (int) $request->input('quarter') : null;

        $runs->queue(
            companyId: $company->id,
            user: $request->user(),
            type: ReportRun::TYPE_VAT_XLSX,
            params: [
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
            ],
        );

        return redirect()
            ->route('reports.runs.index')
            ->with('success', 'Génération de l’export TVA (Excel) en cours — il apparaîtra dans « Mes exports » dès que le worker aura terminé.');
    }

    public function bilan(Request $request, BilanService $service): Response
    {
        $company = app('currentCompany');
        $asOf = $request->input('as_of_date', now()->endOfYear()->toDateString());

        $bilan = $service->compute($company, $asOf);

        return Inertia::render('Reports/Bilan', $bilan);
    }

    /**
     * Queue a bilan PDF render for async generation.
     *
     * Previously this method ran BilanService::compute + Dompdf inline on
     * the HTTP thread, which at scale exceeded request timeouts and
     * burned worker memory. It now enqueues a GenerateBilanPdfJob on the
     * reports queue and redirects the user to the exports page, where
     * the artifact appears once the worker finishes.
     */
    public function queueBilanPdf(Request $request, ReportRunService $runs): RedirectResponse
    {
        $company = app('currentCompany');
        $asOf = $request->input('as_of_date', now()->endOfYear()->toDateString());

        $runs->queue(
            companyId: $company->id,
            user: $request->user(),
            type: ReportRun::TYPE_BILAN_PDF,
            params: ['as_of_date' => $asOf],
        );

        return redirect()
            ->route('reports.runs.index')
            ->with('success', 'Génération du bilan PDF en cours — il apparaîtra ici dès que le worker aura terminé.');
    }

    public function agedReceivables(Request $request, AgedBalanceService $service): Response
    {
        return $this->agedBalance($request, $service, 'receivable');
    }

    public function agedPayables(Request $request, AgedBalanceService $service): Response
    {
        return $this->agedBalance($request, $service, 'payable');
    }

    public function analyticTrialBalance(Request $request, AnalyticReportService $analyticReportService): Response
    {
        $companyId = app('currentCompany')->id;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $axisId = $request->input('axis_id');
        $sectionId = $request->input('section_id');

        $data = $analyticReportService->buildTrialBalance(
            companyId: $companyId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            axisId: $axisId,
            sectionId: $sectionId,
        );

        return Inertia::render('Reports/AnalyticTrialBalance', [
            'rows' => $data['rows'],
            'axes' => $data['axes'],
            'sections' => $data['sections'],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'axis_id' => $axisId,
                'section_id' => $sectionId,
            ],
            'totals' => $data['totals'],
        ]);
    }

    public function queueAnalyticTrialBalanceExport(Request $request, ReportRunService $runs): RedirectResponse
    {
        $company = app('currentCompany');

        $runs->queue(
            companyId: $company->id,
            user: $request->user(),
            type: ReportRun::TYPE_ANALYTIC_TRIAL_BALANCE_XLSX,
            params: [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'axis_id' => $request->input('axis_id'),
                'section_id' => $request->input('section_id'),
            ],
        );

        return redirect()
            ->route('reports.runs.index')
            ->with('success', 'Génération de l’export Balance analytique en cours — il apparaîtra dans « Mes exports ».');
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
