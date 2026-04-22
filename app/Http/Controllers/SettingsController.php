<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AnalyticAxis;
use App\Models\AnalyticSection;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\JournalUserPermission;
use App\Models\User;
use App\Models\JournalEntryLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Company profile
    |--------------------------------------------------------------------------
    */
    public function company(): Response
    {
        return Inertia::render('Settings/Company', [
            'company' => app('currentCompany')->only([
                'id',
                'raison_sociale',
                'forme_juridique',
                'nif',
                'nis',
                'rc',
                'ai',
                'address_line1',
                'address_line2',
                'address_wilaya',
                'address_postal_code',
                'tax_regime',
                'vat_registered',
                'currency',
            ]),
        ]);
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $company = app('currentCompany');

        $validated = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'forme_juridique' => ['required', 'in:SARL,EURL,SPA,SNC,EI,SNCA'],
            'nif' => ['nullable', 'string', 'max:30'],
            'nis' => ['nullable', 'string', 'max:30'],
            'rc' => ['nullable', 'string', 'max:50'],
            'ai' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:500'],
            'address_wilaya' => ['nullable', 'string', 'max:100'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'tax_regime' => ['required', 'string', 'max:50'],
            'vat_registered' => ['required', 'boolean'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $company->update($validated);

        return back()->with('success', 'Paramètres de l’entreprise mis à jour.');
    }

    /**
     * Observability budgets (Phase 0) — surfaced for operators without
     * reading raw env files. Log output still lands in storage/logs.
     */
    public function performance(): Response
    {
        return Inertia::render('Settings/Performance', [
            'perf' => [
                'enabled' => (bool) config('performance.enabled'),
                'slow_query_ms' => (int) config('performance.slow_query_ms'),
                'slow_request_ms' => (int) config('performance.slow_request_ms'),
                'log_path' => storage_path('logs/performance.log'),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Chart of accounts (read-only view)
    |--------------------------------------------------------------------------
    */
    public function accounts(): Response
    {
        $companyId = app('currentCompany')->id;

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->orderBy('class')
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'label',
                'type',
                'class',
                'is_system',
                'is_active',
                'default_analytic_section_id',
            ])
            ->groupBy('class')
            ->map(fn ($items) => $items->values());

        $analyticSections = AnalyticSection::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with('axis:id,code,name')
            ->orderBy('code')
            ->get()
            ->map(fn (AnalyticSection $section) => [
                'id' => $section->id,
                'code' => $section->code,
                'name' => $section->name,
                'axis_code' => $section->axis?->code,
            ])
            ->values();

        return Inertia::render('Settings/Accounts', [
            'accountsByClass' => $accounts,
            'analyticSections' => $analyticSections,
        ]);
    }

    public function updateAccountAnalyticDefault(Request $request, Account $account): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        abort_unless($account->company_id === $companyId, 404);

        $validated = $request->validate([
            'default_analytic_section_id' => [
                'nullable',
                'uuid',
                Rule::exists('analytic_sections', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $account->update([
            'default_analytic_section_id' => $validated['default_analytic_section_id'] ?? null,
        ]);

        return back()->with('success', 'Section analytique par défaut mise à jour.');
    }

    /*
    |--------------------------------------------------------------------------
    | Journals CRUD
    |--------------------------------------------------------------------------
    */
    public function journals(): Response
    {
        $companyId = app('currentCompany')->id;

        $journals = Journal::query()
            ->where('company_id', $companyId)
            ->ordered()
            ->withCount('entries')
            ->with('userPermissions:user_id,journal_id,can_view,can_post')
            ->get()
            ->map(fn (Journal $journal) => [
                'id' => $journal->id,
                'code' => $journal->code,
                'label' => $journal->label,
                'label_ar' => $journal->label_ar,
                'type' => $journal->type,
                'counterpart_account_id' => $journal->counterpart_account_id,
                'allow_auto_counterpart' => $journal->allow_auto_counterpart,
                'is_system' => $journal->is_system,
                'is_active' => $journal->is_active,
                'position' => $journal->position,
                'entries_count' => $journal->entries_count,
                'permissions' => $journal->userPermissions->map(fn ($p) => [
                    'user_id' => $p->user_id,
                    'can_view' => (bool) $p->can_view,
                    'can_post' => (bool) $p->can_post,
                ])->values(),
            ]);

        $users = User::query()
            ->select(['users.id', 'users.name', 'users.email'])
            ->join('company_users', 'company_users.user_id', '=', 'users.id')
            ->where('company_users.company_id', $companyId)
            ->whereNull('company_users.revoked_at')
            ->orderBy('users.name')
            ->distinct()
            ->get();

        $bankAndCashAccounts = Account::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('code', 'LIKE', '512%')
                    ->orWhere('code', 'LIKE', '53%')
                    ->orWhere('code', 'LIKE', '54%');
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label']);

        return Inertia::render('Settings/Journals', [
            'journals' => $journals,
            'users' => $users,
            'counterpartAccounts' => $bankAndCashAccounts,
            'types' => [
                ['value' => 'sales', 'label' => 'Ventes'],
                ['value' => 'purchase', 'label' => 'Achats'],
                ['value' => 'bank', 'label' => 'Banque'],
                ['value' => 'cash', 'label' => 'Caisse'],
                ['value' => 'misc', 'label' => 'Opérations diverses'],
            ],
        ]);
    }

    public function setJournalPermissions(Request $request, Journal $journal): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        abort_unless($journal->company_id === $companyId, 404);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'permissions.*.can_view' => ['required', 'boolean'],
            'permissions.*.can_post' => ['required', 'boolean'],
        ]);

        $userIds = collect($validated['permissions'])->pluck('user_id')->all();

        JournalUserPermission::query()
            ->where('journal_id', $journal->id)
            ->whereNotIn('user_id', $userIds)
            ->delete();

        foreach ($validated['permissions'] as $permission) {
            JournalUserPermission::query()->updateOrCreate(
                [
                    'journal_id' => $journal->id,
                    'user_id' => $permission['user_id'],
                ],
                [
                    'can_view' => (bool) $permission['can_view'],
                    'can_post' => (bool) $permission['can_post'],
                ]
            );
        }

        return back()->with('success', 'Autorisations du journal mises à jour.');
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;

        $validated = $this->validateJournal($request, $companyId);

        Journal::create(array_merge($validated, [
            'company_id' => $companyId,
            'is_system' => false,
        ]));

        return back()->with('success', 'Journal créé.');
    }

    public function updateJournal(Request $request, Journal $journal): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        abort_unless($journal->company_id === $companyId, 404);

        $validated = $this->validateJournal($request, $companyId, $journal);

        if ($journal->is_system) {
            // System journals cannot have their core code/type changed.
            unset($validated['code'], $validated['type']);
        }

        $journal->update($validated);

        return back()->with('success', 'Journal mis à jour.');
    }

    public function destroyJournal(Journal $journal): RedirectResponse
    {
        abort_unless($journal->company_id === app('currentCompany')->id, 404);
        abort_if($journal->is_system, 422, 'Les journaux système ne peuvent pas être supprimés.');

        if ($journal->entries()->exists()) {
            return back()->with('error', 'Ce journal contient des écritures, il ne peut pas être supprimé.');
        }

        $journal->delete();

        return back()->with('success', 'Journal supprimé.');
    }

    protected function validateJournal(Request $request, string $companyId, ?Journal $journal = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('journals', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($journal?->id),
            ],
            'label' => ['required', 'string', 'max:120'],
            'label_ar' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'in:sales,purchase,bank,cash,misc'],
            'counterpart_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'allow_auto_counterpart' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fiscal periods (open / lock)
    |--------------------------------------------------------------------------
    */
    public function periods(): Response
    {
        $companyId = app('currentCompany')->id;

        $periods = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn (FiscalPeriod $p) => [
                'id' => $p->id,
                'year' => $p->year,
                'month' => $p->month,
                'status' => $p->status,
                'locked_at' => $p->locked_at,
                'entries_count' => $p->journalEntries()->count(),
                'unposted_count' => $p->journalEntries()->where('status', 'draft')->count(),
            ])
            ->values();

        return Inertia::render('Settings/Periods', [
            'periods' => $periods,
        ]);
    }

    public function createPeriod(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        FiscalPeriod::firstOrCreate(
            [
                'company_id' => $companyId,
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            [
                'status' => 'open',
            ]
        );

        return back()->with('success', 'Période créée.');
    }

    public function lockPeriod(Request $request, FiscalPeriod $period): RedirectResponse
    {
        abort_unless($period->company_id === app('currentCompany')->id, 404);

        if ($period->isLocked()) {
            return back()->with('error', 'Cette période est déjà verrouillée.');
        }

        if ($period->journalEntries()->where('status', 'draft')->exists()) {
            return back()->with('error', 'Des écritures non validées existent sur cette période.');
        }

        $period->update([
            'status' => 'locked',
            'locked_at' => Carbon::now(),
            'locked_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Période verrouillée.');
    }

    public function reopenPeriod(FiscalPeriod $period): RedirectResponse
    {
        abort_unless($period->company_id === app('currentCompany')->id, 404);

        if (! $period->isLocked()) {
            return back()->with('error', 'Cette période est déjà ouverte.');
        }

        $period->update([
            'status' => 'open',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        return back()->with('success', 'Période rouverte.');
    }

    /*
    |--------------------------------------------------------------------------
    | Bank accounts CRUD
    |--------------------------------------------------------------------------
    */
    public function bankAccounts(): Response
    {
        $companyId = app('currentCompany')->id;

        $accounts = BankAccount::query()
            ->where('company_id', $companyId)
            ->with('glAccount:id,code,label')
            ->orderBy('bank_name')
            ->get()
            ->map(fn (BankAccount $b) => [
                'id' => $b->id,
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'currency' => $b->currency,
                'is_active' => $b->is_active,
                'gl_account' => $b->glAccount ? [
                    'id' => $b->glAccount->id,
                    'code' => $b->glAccount->code,
                    'label' => $b->glAccount->label,
                ] : null,
            ]);

        $glAccounts = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'LIKE', '512%')
                    ->orWhere('code', 'LIKE', '51%');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'label']);

        return Inertia::render('Settings/BankAccounts', [
            'bankAccounts' => $accounts,
            'glAccounts' => $glAccounts,
        ]);
    }

    public function storeBankAccount(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;

        $validated = $this->validateBankAccount($request, $companyId);

        BankAccount::create(array_merge($validated, [
            'company_id' => $companyId,
        ]));

        return back()->with('success', 'Compte bancaire créé.');
    }

    public function updateBankAccount(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        abort_unless($bankAccount->company_id === app('currentCompany')->id, 404);

        $validated = $this->validateBankAccount($request, $bankAccount->company_id, $bankAccount);

        $bankAccount->update($validated);

        return back()->with('success', 'Compte bancaire mis à jour.');
    }

    public function destroyBankAccount(BankAccount $bankAccount): RedirectResponse
    {
        abort_unless($bankAccount->company_id === app('currentCompany')->id, 404);

        if ($bankAccount->transactions()->exists() || $bankAccount->imports()->exists()) {
            return back()->with('error', 'Ce compte bancaire a déjà des mouvements et ne peut pas être supprimé. Désactivez-le à la place.');
        }

        $bankAccount->delete();

        return back()->with('success', 'Compte bancaire supprimé.');
    }

    protected function validateBankAccount(Request $request, string $companyId, ?BankAccount $bank = null): array
    {
        return $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'gl_account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-currency foundations
    |--------------------------------------------------------------------------
    */
    public function currencies(): Response
    {
        $companyId = app('currentCompany')->id;
        $baseCode = strtoupper((string) (app('currentCompany')->currency ?? 'DZD'));

        $currencies = Currency::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'decimals' => $currency->decimals,
                'is_base' => $currency->is_base,
                'is_active' => $currency->is_active,
            ])
            ->values();

        $rates = ExchangeRate::query()
            ->where('company_id', $companyId)
            ->with('currency:id,code,name')
            ->orderByDesc('rate_date')
            ->get()
            ->map(fn (ExchangeRate $rate) => [
                'id' => $rate->id,
                'rate_date' => $rate->rate_date ? (string) $rate->rate_date : null,
                'rate' => (float) $rate->rate,
                'currency' => $rate->currency ? [
                    'id' => $rate->currency->id,
                    'code' => $rate->currency->code,
                    'name' => $rate->currency->name,
                ] : null,
            ])
            ->values();

        return Inertia::render('Settings/Currencies', [
            'baseCurrencyCode' => $baseCode,
            'currencies' => $currencies,
            'rates' => $rates,
        ]);
    }

    public function storeCurrency(Request $request): RedirectResponse
    {
        $company = app('currentCompany');
        $companyId = $company->id;

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:80'],
            'decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'is_active' => ['required', 'boolean'],
        ]);

        Currency::create([
            'company_id' => $companyId,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'decimals' => $validated['decimals'],
            'is_base' => strtoupper($validated['code']) === strtoupper((string) $company->currency),
            'is_active' => (bool) $validated['is_active'],
        ]);

        return back()->with('success', 'Devise créée.');
    }

    public function updateCurrency(Request $request, Currency $currency): RedirectResponse
    {
        $company = app('currentCompany');
        abort_unless($currency->company_id === $company->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (strtoupper($currency->code) === strtoupper((string) $company->currency)) {
            $validated['is_active'] = true;
        }

        $currency->update($validated);

        return back()->with('success', 'Devise mise à jour.');
    }

    public function destroyCurrency(Currency $currency): RedirectResponse
    {
        $company = app('currentCompany');
        abort_unless($currency->company_id === $company->id, 404);

        if (strtoupper($currency->code) === strtoupper((string) $company->currency)) {
            return back()->with('error', 'La devise de base de la société ne peut pas être supprimée.');
        }

        if ($currency->exchangeRates()->exists() || DB::table('journal_lines')->where('currency_id', $currency->id)->exists()) {
            return back()->with('error', 'Cette devise est utilisée et ne peut pas être supprimée.');
        }

        $currency->delete();

        return back()->with('success', 'Devise supprimée.');
    }

    public function storeExchangeRate(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;

        $validated = $request->validate([
            'currency_id' => [
                'required',
                'uuid',
                Rule::exists('currencies', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'rate_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0'],
        ]);

        ExchangeRate::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'currency_id' => $validated['currency_id'],
                'rate_date' => $validated['rate_date'],
            ],
            [
                'rate' => $validated['rate'],
            ]
        );

        return back()->with('success', 'Taux enregistré.');
    }

    public function destroyExchangeRate(ExchangeRate $exchangeRate): RedirectResponse
    {
        abort_unless($exchangeRate->company_id === app('currentCompany')->id, 404);
        $exchangeRate->delete();

        return back()->with('success', 'Taux supprimé.');
    }

    /*
    |--------------------------------------------------------------------------
    | Analytic accounting (axes / sections)
    |--------------------------------------------------------------------------
    */
    public function analytics(): Response
    {
        $companyId = app('currentCompany')->id;

        $axes = AnalyticAxis::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get()
            ->map(fn (AnalyticAxis $axis) => [
                'id' => $axis->id,
                'code' => $axis->code,
                'name' => $axis->name,
                'is_active' => $axis->is_active,
            ])
            ->values();

        $sections = AnalyticSection::query()
            ->where('company_id', $companyId)
            ->with('axis:id,code,name')
            ->orderBy('code')
            ->get()
            ->map(fn (AnalyticSection $section) => [
                'id' => $section->id,
                'code' => $section->code,
                'name' => $section->name,
                'is_active' => $section->is_active,
                'analytic_axis_id' => $section->analytic_axis_id,
                'axis' => $section->axis ? [
                    'id' => $section->axis->id,
                    'code' => $section->axis->code,
                    'name' => $section->axis->name,
                ] : null,
            ])
            ->values();

        return Inertia::render('Settings/Analytics', [
            'axes' => $axes,
            'sections' => $sections,
        ]);
    }

    public function storeAnalyticAxis(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('analytic_axes', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        AnalyticAxis::create(array_merge($validated, ['company_id' => $companyId]));

        return back()->with('success', 'Axe analytique créé.');
    }

    public function updateAnalyticAxis(Request $request, AnalyticAxis $axis): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        abort_unless($axis->company_id === $companyId, 404);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('analytic_axes', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($axis->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        $axis->update($validated);

        return back()->with('success', 'Axe analytique mis à jour.');
    }

    public function destroyAnalyticAxis(AnalyticAxis $axis): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        abort_unless($axis->company_id === $companyId, 404);

        if ($axis->sections()->exists()) {
            return back()->with('error', 'Supprimez d’abord les sections de cet axe.');
        }

        $axis->delete();

        return back()->with('success', 'Axe analytique supprimé.');
    }

    public function storeAnalyticSection(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        $validated = $request->validate([
            'analytic_axis_id' => [
                'required',
                'uuid',
                Rule::exists('analytic_axes', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        AnalyticSection::create(array_merge($validated, ['company_id' => $companyId]));

        return back()->with('success', 'Section analytique créée.');
    }

    public function entryLocks(): Response
    {
        $company = app('currentCompany');
        $dateLock = JournalEntryLock::query()
            ->where('company_id', $company->id)
            ->where('lock_type', 'date')
            ->orderByDesc('locked_until_date')
            ->first();

        return Inertia::render('Settings/EntryLocks', [
            'lock' => [
                'locked_until_date' => $dateLock?->locked_until_date ? (string) $dateLock->locked_until_date : null,
                'has_password' => ! empty($company->period_lock_password_hash),
            ],
        ]);
    }

    public function setEntryLockPassword(Request $request): RedirectResponse
    {
        $company = app('currentCompany');
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ]);

        $company->update([
            'period_lock_password_hash' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Mot de passe de verrouillage mis à jour.');
    }

    public function setDateEntryLock(Request $request): RedirectResponse
    {
        $company = app('currentCompany');
        $validated = $request->validate([
            'locked_until_date' => ['required', 'date'],
            'password' => ['nullable', 'string'],
        ]);

        if (! empty($company->period_lock_password_hash)) {
            if (! Hash::check((string) ($validated['password'] ?? ''), $company->period_lock_password_hash)) {
                return back()->with('error', 'Mot de passe de verrouillage invalide.');
            }
        }

        JournalEntryLock::query()
            ->where('company_id', $company->id)
            ->where('lock_type', 'date')
            ->delete();

        JournalEntryLock::query()->create([
            'company_id' => $company->id,
            'lock_type' => 'date',
            'locked_until_date' => $validated['locked_until_date'],
            'locked_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Verrouillage par date appliqué.');
    }

    public function clearDateEntryLock(Request $request): RedirectResponse
    {
        $company = app('currentCompany');
        $validated = $request->validate([
            'password' => ['nullable', 'string'],
        ]);

        if (! empty($company->period_lock_password_hash)) {
            if (! Hash::check((string) ($validated['password'] ?? ''), $company->period_lock_password_hash)) {
                return back()->with('error', 'Mot de passe de verrouillage invalide.');
            }
        }

        JournalEntryLock::query()
            ->where('company_id', $company->id)
            ->where('lock_type', 'date')
            ->delete();

        return back()->with('success', 'Verrouillage par date retiré.');
    }

    public function updateAnalyticSection(Request $request, AnalyticSection $section): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        abort_unless($section->company_id === $companyId, 404);

        $validated = $request->validate([
            'analytic_axis_id' => [
                'required',
                'uuid',
                Rule::exists('analytic_axes', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        $section->update($validated);

        return back()->with('success', 'Section analytique mise à jour.');
    }

    public function destroyAnalyticSection(AnalyticSection $section): RedirectResponse
    {
        $companyId = app('currentCompany')->id;
        abort_unless($section->company_id === $companyId, 404);

        if ($section->journalLines()->exists()) {
            return back()->with('error', 'Cette section est déjà utilisée dans des écritures.');
        }

        $linkedAccounts = Account::query()
            ->where('company_id', $companyId)
            ->where('default_analytic_section_id', $section->id)
            ->count();

        if ($linkedAccounts > 0) {
            return back()->with('error', 'Cette section est utilisée comme défaut sur des comptes.');
        }

        $section->delete();

        return back()->with('success', 'Section analytique supprimée.');
    }
}
