<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ])
            ->groupBy('class')
            ->map(fn ($items) => $items->values());

        return Inertia::render('Settings/Accounts', [
            'accountsByClass' => $accounts,
        ]);
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
            ->get()
            ->map(fn (Journal $journal) => [
                'id' => $journal->id,
                'code' => $journal->code,
                'label' => $journal->label,
                'label_ar' => $journal->label_ar,
                'type' => $journal->type,
                'counterpart_account_id' => $journal->counterpart_account_id,
                'is_system' => $journal->is_system,
                'is_active' => $journal->is_active,
                'position' => $journal->position,
                'entries_count' => $journal->entries_count,
            ]);

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
}
