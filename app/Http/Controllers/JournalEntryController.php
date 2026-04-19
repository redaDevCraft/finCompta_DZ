<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Carbon\Carbon;
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
    public function __construct(protected JournalService $journalService) {}

    public function create(Request $request): Response
    {
        $company = app('currentCompany');

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
            'accounts' => $this->loadAccounts($company),
            'contacts' => $this->loadContacts($company),
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

        return redirect()
            ->route('ledger.journal')
            ->with('success', 'Écriture enregistrée.');
    }

    public function edit(JournalEntry $entry): Response
    {
        $this->authorizeEntry($entry);

        abort_if($entry->status !== 'draft', 422, 'Seules les écritures en brouillon sont modifiables.');

        $company = app('currentCompany');

        $entry->load(['lines.account', 'lines.contact', 'journal']);

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
                        'description' => $line->description,
                        'debit' => (float) $line->debit,
                        'credit' => (float) $line->credit,
                    ];
                })->values(),
            ],
            'journals' => $this->loadJournals($company),
            'accounts' => $this->loadAccounts($company),
            'contacts' => $this->loadContacts($company),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, JournalEntry $entry, JournalService $journalService): RedirectResponse
    {
        $this->authorizeEntry($entry);

        abort_if($entry->status !== 'draft', 422, 'Seules les écritures en brouillon sont modifiables.');

        $company = app('currentCompany');

        $validated = $this->validatePayload($request, $company);

        DB::transaction(function () use ($entry, $validated, $company, $journalService) {
            $journal = Journal::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $validated['journal_id'])
                ->where('is_active', true)
                ->firstOrFail();

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

        return redirect()
            ->route('ledger.journal')
            ->with('success', 'Écriture mise à jour.');
    }

    public function destroy(JournalEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($entry);

        abort_if($entry->status !== 'draft', 422, 'Seules les écritures en brouillon peuvent être supprimées.');
        abort_if($entry->source_type !== 'manual' && $entry->source_type !== null, 422, 'Cette écriture provient d’un document et ne peut être supprimée directement.');

        DB::transaction(function () use ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });

        return redirect()
            ->route('ledger.journal')
            ->with('success', 'Écriture supprimée.');
    }

    private function emptyLine(): array
    {
        return [
            'account_id' => null,
            'contact_id' => null,
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
            ->orderBy('position')
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'type', 'counterpart_account_id'])
            ->toArray();
    }

    private function loadAccounts(Company $company): array
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'class', 'type'])
            ->toArray();
    }

    private function loadContacts(Company $company): array
    {
        return Contact::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'type'])
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
            'lines.*.description' => ['nullable', 'string', 'max:500'],
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
        abort_unless(
            $entry->company_id === app('currentCompany')->id,
            403,
            'Accès non autorisé à cette écriture.'
        );
    }
}
