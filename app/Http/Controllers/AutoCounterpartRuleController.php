<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AutoCounterpartRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutoCounterpartRuleController extends Controller
{
    public function index(): Response
    {
        $companyId = app('currentCompany')->id;

        $rules = AutoCounterpartRule::query()
            ->where('company_id', $companyId)
            ->with(['triggerAccount:id,code,label', 'counterpartAccount:id,code,label'])
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (AutoCounterpartRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'trigger_account_id' => $rule->trigger_account_id,
                'trigger_direction' => $rule->trigger_direction,
                'counterpart_account_id' => $rule->counterpart_account_id,
                'counterpart_direction' => $rule->counterpart_direction,
                'priority' => $rule->priority,
                'is_active' => $rule->is_active,
                'trigger_account' => $rule->triggerAccount ? [
                    'id' => $rule->triggerAccount->id,
                    'code' => $rule->triggerAccount->code,
                    'label' => $rule->triggerAccount->label,
                ] : null,
                'counterpart_account' => $rule->counterpartAccount ? [
                    'id' => $rule->counterpartAccount->id,
                    'code' => $rule->counterpartAccount->code,
                    'label' => $rule->counterpartAccount->label,
                ] : null,
            ])
            ->values();

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label']);

        return Inertia::render('Settings/AutoCounterpartRules', [
            'rules' => $rules,
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        $validated = $this->validateRule($request, $companyId);
        AutoCounterpartRule::create(array_merge($validated, ['company_id' => $companyId]));

        return back()->with('success', 'Règle de contrepartie créée.');
    }

    public function update(Request $request, AutoCounterpartRule $rule): RedirectResponse
    {
        abort_unless($rule->company_id === app('currentCompany')->id, 404);
        $validated = $this->validateRule($request, $rule->company_id);
        $rule->update($validated);

        return back()->with('success', 'Règle de contrepartie mise à jour.');
    }

    public function destroy(AutoCounterpartRule $rule): RedirectResponse
    {
        abort_unless($rule->company_id === app('currentCompany')->id, 404);
        $rule->delete();

        return back()->with('success', 'Règle de contrepartie supprimée.');
    }

    private function validateRule(Request $request, string $companyId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger_account_id' => ['required', 'uuid', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'trigger_direction' => ['required', 'in:debit,credit'],
            'counterpart_account_id' => [
                'required',
                'uuid',
                'different:trigger_account_id',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'counterpart_direction' => ['required', 'in:debit,credit'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ], [
            'counterpart_account_id.different' => 'Le compte de contrepartie doit être différent du compte déclencheur.',
        ]);
    }
}
