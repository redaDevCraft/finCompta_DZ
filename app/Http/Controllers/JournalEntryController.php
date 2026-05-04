<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\AutoCounterpartRule;
use App\Models\Currency;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\AnalyticSection;
use App\Services\EntryLockService;
use App\Services\JournalPermissionService;
use App\Services\JournalService;
use App\Support\Cache\DashboardCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function __construct(
        protected JournalService $journalService,
        protected EntryLockService $entryLockService,
        protected JournalPermissionService $journalPermissionService,
    ) {}

    public function create(Request $request): Response
    {
        $company = app('currentCompany');

        // New entry: no preselected accounts/contacts. Journals are typically
        // under a dozen and are the entry-point of the form, so we keep them
        // eager. Account + contact pickers use /suggest/* async lookups.
        return Inertia::render('Ledger/Entries/Create', [
            'form' => [
                'entry_date' => $request->query('entry_date', now()->toDateString()),
                'journal_id' => $request->query('journal_id'),
                'reference' => '',
                'description' => '',
                'lines' => [
                    $this->emptyLine(),
                    $this->emptyLine(),
                ],
            ],
            'journals' => $this->loadJournals($company),
            'prefillAccounts' => [],
            'prefillContacts' => [],
            'analyticSections' => $this->loadAnalyticSections($company),
            'autoCounterpartRules' => $this->loadAutoCounterpartRules($company),
            'currencies' => $this->loadCurrencies($company),
        ]);
    }

    public function store(Request $request, JournalService $journalService): RedirectResponse
    {
        $company = app('currentCompany');

        $validated = $this->validatePayload($request, $company);

        $entry = DB::transaction(function () use ($validated, $company, $journalService) {
            $journal = Journal::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $validated['journal_id'])
                ->where('is_active', true)
                ->firstOrFail();
            abort_unless(
                $this->journalPermissionService->canPost(request()->user(), $journal),
                403,
                'Vous n’êtes pas autorisé à comptabiliser dans ce journal.'
            );

            $period = $journalService->getOrCreatePeriod(
                $company,
                Carbon::parse($validated['entry_date'])
            );

            $entry = JournalEntry::create([
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'period_id' => $period->id,
                'journal_id' => $journal->id,
                'entry_date' => $validated['entry_date'],
                'journal_code' => $journal->code,
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => 'draft',
                'source_type' => 'manual',
                'source_id' => null,
                'posted_at' => null,
                'posted_by' => null,
            ]);

            foreach ($validated['lines'] as $index => $line) {
                $entry->lines()->create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $line['account_id'],
                    'contact_id' => $line['contact_id'] ?? null,
                    'analytic_section_id' => $line['analytic_section_id'] ?? null,
                    'currency_id' => $line['currency_id'] ?? null,
                    'exchange_rate' => $line['exchange_rate'] ?? null,
                    'amount_foreign_debit' => $line['amount_foreign_debit'] ?? null,
                    'amount_foreign_credit' => $line['amount_foreign_credit'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                    'sort_order' => $index,
                ]);
            }

            $entry->load('lines');

            if (! $entry->isBalanced()) {
                abort(422, 'Écriture comptable déséquilibrée');
            }

            return $entry;
        });

        if ($request->boolean('post_immediately')) {
            $journalService->post($entry->fresh('lines'), $request->user());
        }

        // New journal entry → recent entries list, class-6/7 aggregates,
        // account balances all shift. Only posted entries affect KPIs, but
        // we forget unconditionally: the cost is a single INCR, and keeping
        // the rule simple avoids drift between branches.
        DashboardCache::forget($company->id);

        return redirect()
            ->route('ledger.journal')
            ->with('success', 'Écriture enregistrée.');
    }

    public function edit(JournalEntry $entry): Response
    {
        $this->authorizeEntry($entry);
        abort_if($this->entryLockService->isLocked($entry), 422, 'Cette écriture est verrouillée.');

        abort_if($entry->status !== 'draft', 422, 'Seules les écritures en brouillon sont modifiables.');

        $company = app('currentCompany');

        $entry->load(['lines.account:id,code,label', 'lines.contact:id,display_name,type', 'journal']);

        // Prefill maps: only the accounts and contacts actually referenced by
        // this draft's lines. That's at most ~2*N rows where N = line count,
        // versus the tens of thousands of rows a large tenant has in total.
        // The AsyncCombobox consumes these as initial `prefill` values so
        // the user sees the selected items immediately, then hits
        // /suggest/* only when they open the picker.
        $prefillAccounts = $entry->lines
            ->pluck('account')
            ->filter()
            ->unique('id')
            ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'label' => $a->label])
            ->values();

        $prefillContacts = $entry->lines
            ->pluck('contact')
            ->filter()
            ->unique('id')
            ->map(fn ($c) => ['id' => $c->id, 'display_name' => $c->display_name, 'type' => $c->type])
            ->values();

        return Inertia::render('Ledger/Entries/Create', [
            'form' => [
                'id' => $entry->id,
                'entry_date' => optional($entry->entry_date)->toDateString(),
                'journal_id' => $entry->journal_id,
                'reference' => $entry->reference,
                'description' => $entry->description,
                'lines' => $entry->lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'account_id' => $line->account_id,
                        'contact_id' => $line->contact_id,
                        'analytic_section_id' => $line->analytic_section_id,
                        'currency_id' => $line->currency_id,
                        'exchange_rate' => $line->exchange_rate !== null ? (float) $line->exchange_rate : null,
                        'amount_foreign_debit' => $line->amount_foreign_debit !== null ? (float) $line->amount_foreign_debit : null,
                        'amount_foreign_credit' => $line->amount_foreign_credit !== null ? (float) $line->amount_foreign_credit : null,
                        'description' => $line->description,
                        'debit' => (float) $line->debit,
                        'credit' => (float) $line->credit,
                    ];
                })->values(),
            ],
            'journals' => $this->loadJournals($company),
            'prefillAccounts' => $prefillAccounts,
            'prefillContacts' => $prefillContacts,
            'analyticSections' => $this->loadAnalyticSections($company),
            'autoCounterpartRules' => $this->loadAutoCounterpartRules($company),
            'currencies' => $this->loadCurrencies($company),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, JournalEntry $entry, JournalService $journalService): RedirectResponse
    {
        $this->authorizeEntry($entry);
        abort_if($this->entryLockService->isLocked($entry), 422, 'Cette écriture est verrouillée.');

        abort_if($entry->status !== 'draft', 422, 'Seules les écritures en brouillon sont modifiables.');

        $company = app('currentCompany');

        $validated = $this->validatePayload($request, $company);

        DB::transaction(function () use ($entry, $validated, $company, $journalService) {
            $journal = Journal::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $validated['journal_id'])
                ->where('is_active', true)
                ->firstOrFail();
            abort_unless(
                $this->journalPermissionService->canPost(request()->user(), $journal),
                403,
                'Vous n’êtes pas autorisé à comptabiliser dans ce journal.'
            );

            $period = $journalService->getOrCreatePeriod(
                $company,
                Carbon::parse($validated['entry_date'])
            );

            $entry->update([
                'period_id' => $period->id,
                'journal_id' => $journal->id,
                'entry_date' => $validated['entry_date'],
                'journal_code' => $journal->code,
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            $entry->lines()->delete();

            foreach ($validated['lines'] as $index => $line) {
                $entry->lines()->create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $line['account_id'],
                    'contact_id' => $line['contact_id'] ?? null,
                    'analytic_section_id' => $line['analytic_section_id'] ?? null,
                    'currency_id' => $line['currency_id'] ?? null,
                    'exchange_rate' => $line['exchange_rate'] ?? null,
                    'amount_foreign_debit' => $line['amount_foreign_debit'] ?? null,
                    'amount_foreign_credit' => $line['amount_foreign_credit'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                    'sort_order' => $index,
                ]);
            }

            $entry->load('lines');

            if (! $entry->isBalanced()) {
                abort(422, 'Écriture comptable déséquilibrée');
            }
        });

        DashboardCache::forget($company->id);

        return redirect()
            ->route('ledger.journal')
            ->with('success', 'Écriture mise à jour.');
    }

    /**
     * Return the lines for a single journal entry as JSON.
     *
     * Used by the journal index page to lazy-load line details when a user
     * expands a row. Keeps the list endpoint payload small (only aggregate
     * totals) while still allowing drill-down on demand.
     */
    public function lines(JournalEntry $entry): JsonResponse
    {
        $this->authorizeEntry($entry);
        abort_if($this->entryLockService->isLocked($entry), 422, 'Cette écriture est verrouillée.');

        $entry->load([
            'lines' => function ($query) {
                $query->with([
                    'account:id,code,label',
                    'contact:id,display_name',
                    'analyticSection:id,code,name,analytic_axis_id',
                ])->orderBy('sort_order');
            },
        ]);

        return response()->json([
            'entry_id' => $entry->id,
            'lines' => $entry->lines->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'sort_order' => $line->sort_order,
                'currency_id' => $line->currency_id,
                'exchange_rate' => $line->exchange_rate !== null ? (float) $line->exchange_rate : null,
                'amount_foreign_debit' => $line->amount_foreign_debit !== null ? (float) $line->amount_foreign_debit : null,
                'amount_foreign_credit' => $line->amount_foreign_credit !== null ? (float) $line->amount_foreign_credit : null,
                'account' => $line->account ? [
                    'id' => $line->account->id,
                    'code' => $line->account->code,
                    'label' => $line->account->label,
                ] : null,
                'contact' => $line->contact ? [
                    'id' => $line->contact->id,
                    'display_name' => $line->contact->display_name,
                ] : null,
                'analytic_section' => $line->analyticSection ? [
                    'id' => $line->analyticSection->id,
                    'code' => $line->analyticSection->code,
                    'name' => $line->analyticSection->name,
                    'analytic_axis_id' => $line->analyticSection->analytic_axis_id,
                ] : null,
            ])->values(),
        ]);
    }

    public function destroy(JournalEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($entry);
        abort_if($this->entryLockService->isLocked($entry), 422, 'Cette écriture est verrouillée.');

        abort_if($entry->status !== 'draft', 422, 'Seules les écritures en brouillon peuvent être supprimées.');
        abort_if($entry->source_type !== 'manual' && $entry->source_type !== null, 422, 'Cette écriture provient d’un document et ne peut être supprimée directement.');

        DB::transaction(function () use ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });

        DashboardCache::forget($entry->company_id);

        return redirect()
            ->route('ledger.journal')
            ->with('success', 'Écriture supprimée.');
    }

    private function emptyLine(): array
    {
        return [
            'account_id' => null,
            'contact_id' => null,
            'analytic_section_id' => null,
            'currency_id' => null,
            'exchange_rate' => null,
            'amount_foreign_debit' => null,
            'amount_foreign_credit' => null,
            'description' => '',
            'debit' => 0,
            'credit' => 0,
        ];
    }

    private function loadJournals(Company $company): array
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $userId = request()->user()?->id;
                $query
                    ->whereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('journal_user_permissions as jup_all')
                            ->whereColumn('jup_all.journal_id', 'journals.id');
                    })
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->selectRaw('1')
                            ->from('journal_user_permissions as jup')
                            ->whereColumn('jup.journal_id', 'journals.id')
                            ->where('jup.user_id', $userId)
                            ->where('jup.can_post', true);
                    });
            })
            ->orderBy('position')
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'type', 'counterpart_account_id', 'allow_auto_counterpart'])
            ->toArray();
    }

    private function loadAutoCounterpartRules(Company $company): array
    {
        return AutoCounterpartRule::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->with(['triggerAccount:id,code,label', 'counterpartAccount:id,code,label'])
            ->get()
            ->map(fn (AutoCounterpartRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'trigger_account_id' => $rule->trigger_account_id,
                'trigger_direction' => $rule->trigger_direction,
                'counterpart_account_id' => $rule->counterpart_account_id,
                'counterpart_direction' => $rule->counterpart_direction,
                'priority' => $rule->priority,
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
            ->values()
            ->toArray();
    }

    private function loadAnalyticSections(Company $company): array
    {
        return AnalyticSection::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->with('axis:id,code,name')
            ->orderBy('code')
            ->get()
            ->map(fn (AnalyticSection $section) => [
                'id' => $section->id,
                'code' => $section->code,
                'name' => $section->name,
                'analytic_axis_id' => $section->analytic_axis_id,
                'axis_code' => $section->axis?->code,
                'axis_name' => $section->axis?->name,
            ])
            ->values()
            ->toArray();
    }

    private function loadCurrencies(Company $company): array
    {
        return Currency::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'decimals', 'is_base'])
            ->toArray();
    }

    private function validatePayload(Request $request, Company $company): array
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'journal_id' => [
                'required',
                'uuid',
                Rule::exists('journals', 'id')
                    ->where('company_id', $company->id)
                    ->where('is_active', true),
            ],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')
                    ->where('company_id', $company->id)
                    ->where('is_active', true),
            ],
            'lines.*.contact_id' => [
                'nullable',
                'uuid',
                Rule::exists('contacts', 'id')->where('company_id', $company->id),
            ],
            'lines.*.analytic_section_id' => [
                'nullable',
                'uuid',
                Rule::exists('analytic_sections', 'id')->where('company_id', $company->id),
            ],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.currency_id' => [
                'nullable',
                'uuid',
                Rule::exists('currencies', 'id')->where('company_id', $company->id),
            ],
            'lines.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.amount_foreign_debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.amount_foreign_credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'post_immediately' => ['sometimes', 'boolean'],
        ]);

        // Enforce double-entry at request level so user gets a clean validation error
        // instead of a 422 abort deep inside the service.
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($data['lines'] as $i => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                Validator::make([], [])->after(function ($v) use ($i) {
                    $v->errors()->add("lines.{$i}.debit", 'Les montants doivent être positifs.');
                })->validate();
            }

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    "lines.{$i}.debit" => ['Une ligne ne peut avoir un débit et un crédit en même temps.'],
                ]);
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw ValidationException::withMessages([
                    "lines.{$i}.debit" => ['Saisir un débit ou un crédit.'],
                ]);
            }

            $fxDebit = (float) ($line['amount_foreign_debit'] ?? 0);
            $fxCredit = (float) ($line['amount_foreign_credit'] ?? 0);
            $hasFx = ! empty($line['currency_id']) || ! empty($line['exchange_rate']) || $fxDebit > 0 || $fxCredit > 0;

            if ($hasFx && empty($line['currency_id'])) {
                throw ValidationException::withMessages([
                    "lines.{$i}.currency_id" => ['Sélectionnez une devise pour les montants en devise.'],
                ]);
            }

            if ($hasFx && empty($line['exchange_rate'])) {
                throw ValidationException::withMessages([
                    "lines.{$i}.exchange_rate" => ['Saisir un taux de change strictement positif.'],
                ]);
            }

            if ($fxDebit > 0 && $fxCredit > 0) {
                throw ValidationException::withMessages([
                    "lines.{$i}.amount_foreign_debit" => ['Une ligne ne peut avoir un débit et un crédit en devise en même temps.'],
                ]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            throw ValidationException::withMessages([
                'lines' => [sprintf(
                    'L’écriture n’est pas équilibrée (débit %s ≠ crédit %s).',
                    number_format($totalDebit, 2, ',', ' '),
                    number_format($totalCredit, 2, ',', ' '),
                )],
            ]);
        }

        return $data;
    }

    private function authorizeEntry(JournalEntry $entry): void
    {
        abort_unless($entry->company_id === app('currentCompany')->id, 404);
    
        $journal = Journal::withoutGlobalScopes()
            ->where('company_id', app('currentCompany')->id)
            ->where('id', $entry->journal_id)
            ->first();
    
        abort_if(! $journal, 404, 'Journal introuvable pour cette écriture.'); // ← clear 404
    
        abort_unless(
            $this->journalPermissionService->canView(request()->user(), $journal),
            403,
            'Accès non autorisé à cette écriture.'
        );
    }

}
