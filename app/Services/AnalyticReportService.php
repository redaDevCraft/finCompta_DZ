<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticAxis;
use App\Models\AnalyticSection;
use Illuminate\Support\Facades\DB;

final class AnalyticReportService
{
    /**
     * @return array{
     *   rows: \Illuminate\Support\Collection<int, array{
     *     account_id: string,
     *     account_code: string,
     *     account_label: string,
     *     axis_id: string|null,
     *     axis_code: string|null,
     *     axis_name: string|null,
     *     section_id: string|null,
     *     section_code: string|null,
     *     section_name: string|null,
     *     debit: float,
     *     credit: float,
     *     balance: float
     *   }>,
     *   axes: \Illuminate\Database\Eloquent\Collection<int, \App\Models\AnalyticAxis>,
     *   sections: \Illuminate\Database\Eloquent\Collection<int, \App\Models\AnalyticSection>,
     *   totals: array{debit: float, credit: float, balance: float}
     * }
     */
    public function buildTrialBalance(
        string $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $axisId = null,
        ?string $sectionId = null,
    ): array {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->leftJoin('analytic_sections', 'analytic_sections.id', '=', 'journal_lines.analytic_section_id')
            ->leftJoin('analytic_axes', 'analytic_axes.id', '=', 'analytic_sections.analytic_axis_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->when($dateFrom, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $dateTo))
            ->when($axisId, fn ($q) => $q->where('analytic_sections.analytic_axis_id', $axisId))
            ->when($sectionId, fn ($q) => $q->where('journal_lines.analytic_section_id', $sectionId))
            ->groupBy([
                'accounts.id',
                'accounts.code',
                'accounts.label',
                'analytic_sections.id',
                'analytic_sections.code',
                'analytic_sections.name',
                'analytic_axes.id',
                'analytic_axes.code',
                'analytic_axes.name',
            ])
            ->orderBy('accounts.code')
            ->orderBy('analytic_sections.code')
            ->select([
                'accounts.id as account_id',
                'accounts.code as account_code',
                'accounts.label as account_label',
                'analytic_sections.id as section_id',
                'analytic_sections.code as section_code',
                'analytic_sections.name as section_name',
                'analytic_axes.id as axis_id',
                'analytic_axes.code as axis_code',
                'analytic_axes.name as axis_name',
                DB::raw('COALESCE(SUM(journal_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_lines.credit), 0) as total_credit'),
            ])
            ->get()
            ->map(fn ($row) => [
                'account_id' => $row->account_id,
                'account_code' => $row->account_code,
                'account_label' => $row->account_label,
                'axis_id' => $row->axis_id,
                'axis_code' => $row->axis_code,
                'axis_name' => $row->axis_name,
                'section_id' => $row->section_id,
                'section_code' => $row->section_code,
                'section_name' => $row->section_name,
                'debit' => (float) $row->total_debit,
                'credit' => (float) $row->total_credit,
                'balance' => (float) $row->total_debit - (float) $row->total_credit,
            ])
            ->values();

        $axes = AnalyticAxis::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $sections = AnalyticSection::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'analytic_axis_id', 'code', 'name']);

        return [
            'rows' => $rows,
            'axes' => $axes,
            'sections' => $sections,
            'totals' => [
                'debit' => (float) $rows->sum('debit'),
                'credit' => (float) $rows->sum('credit'),
                'balance' => (float) $rows->sum('balance'),
            ],
        ];
    }
}
