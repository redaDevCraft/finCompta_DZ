<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AnalyticSection;
use App\Models\ManagementPrediction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ManagementPredictionController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app('currentCompany');
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        $predictions = ManagementPrediction::query()
            ->where('company_id', $company->id)
            ->with(['account:id,code,label', 'contact:id,display_name', 'analyticSection:id,code,name'])
            ->orderByDesc('period_start_date')
            ->get()
            ->map(fn (ManagementPrediction $p) => [
                'id' => $p->id,
                'account' => $p->account ? ['id' => $p->account->id, 'code' => $p->account->code, 'label' => $p->account->label] : null,
                'contact' => $p->contact ? ['id' => $p->contact->id, 'display_name' => $p->contact->display_name] : null,
                'analytic_section' => $p->analyticSection ? ['id' => $p->analyticSection->id, 'code' => $p->analyticSection->code, 'name' => $p->analyticSection->name] : null,
                'period_type' => $p->period_type,
                'period_start_date' => optional($p->period_start_date)->toDateString(),
                'period_end_date' => optional($p->period_end_date)->toDateString(),
                'amount' => (float) $p->amount,
                'comment' => $p->comment,
            ])->values();

        $actualRows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.label')
            ->select([
                'accounts.id as account_id',
                'accounts.code as account_code',
                'accounts.label as account_label',
                DB::raw('COALESCE(SUM(journal_lines.debit - journal_lines.credit),0) as actual_amount'),
            ])
            ->get()
            ->map(fn ($row) => [
                'account_id' => $row->account_id,
                'account_code' => $row->account_code,
                'account_label' => $row->account_label,
                'actual' => (float) $row->actual_amount,
            ]);

        $budgetByAccount = ManagementPrediction::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start_date', '<=', $to)
            ->whereDate('period_end_date', '>=', $from)
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->select(['account_id', DB::raw('COALESCE(SUM(amount),0) as budget_amount')])
            ->get()
            ->keyBy('account_id');

        $actualVsBudget = $actualRows->map(function ($row) use ($budgetByAccount) {
            $budget = (float) ($budgetByAccount[$row['account_id']]->budget_amount ?? 0);
            $actual = (float) $row['actual'];
            $variance = $actual - $budget;
            return [
                ...$row,
                'budget' => $budget,
                'variance' => $variance,
                'variance_pct' => $budget != 0.0 ? ($variance / abs($budget)) * 100 : null,
            ];
        })->values();

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label']);
        $sections = AnalyticSection::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Reports/Predictions', [
            'enabled' => (bool) $company->management_predictions_enabled,
            'predictions' => $predictions,
            'actualVsBudget' => $actualVsBudget,
            'accounts' => $accounts,
            'sections' => $sections,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $company = app('currentCompany');
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $company->update(['management_predictions_enabled' => $validated['enabled']]);

        return back()->with('success', 'Prévisions de gestion mises à jour.');
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        $validated = $request->validate([
            'account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'contact_id' => ['nullable', 'uuid', Rule::exists('contacts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'analytic_section_id' => ['nullable', 'uuid', Rule::exists('analytic_sections', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'period_type' => ['required', 'in:month,quarter,year'],
            'period_start_date' => ['required', 'date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'amount' => ['required', 'numeric'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($validated['account_id']) && empty($validated['contact_id']) && empty($validated['analytic_section_id'])) {
            return back()->with('error', 'Choisissez au moins un axe: compte, tiers ou section analytique.');
        }

        ManagementPrediction::create(array_merge($validated, ['company_id' => $companyId]));
        return back()->with('success', 'Prévision enregistrée.');
    }

    public function destroy(ManagementPrediction $prediction): RedirectResponse
    {
        abort_unless($prediction->company_id === app('currentCompany')->id, 404);
        $prediction->delete();
        return back()->with('success', 'Prévision supprimée.');
    }
}
