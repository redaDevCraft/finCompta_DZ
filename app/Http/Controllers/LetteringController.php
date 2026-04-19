<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Lettering;
use App\Services\LetteringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LetteringController extends Controller
{
    public function __construct(protected LetteringService $service) {}

    public function index(Request $request): Response
    {
        $company = app('currentCompany');

        $lettrableAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('is_lettrable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'class', 'type']);

        $contacts = Contact::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'type']);

        $selectedAccountId = $request->input('account_id');
        $selectedContactId = $request->input('contact_id');

        $account = null;
        $unletteredLines = collect();
        $letterings = collect();
        $openBalance = 0.0;

        if ($selectedAccountId) {
            $account = $lettrableAccounts->firstWhere('id', $selectedAccountId);
        }

        $contact = null;
        if ($selectedContactId) {
            $contact = $contacts->firstWhere('id', $selectedContactId);
        }

        if ($account) {
            $lines = $this->service->unletteredLines($account, $contact);

            $unletteredLines = $lines->map(function ($line) {
                $e = $line->journalEntry;

                return [
                    'id' => $line->id,
                    'entry_date' => optional($e?->entry_date)->toDateString() ?? optional($e)->entry_date,
                    'journal_code' => $e?->journal_code,
                    'reference' => $e?->reference,
                    'description' => $line->description ?: $e?->description,
                    'contact_id' => $line->contact_id,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ];
            })->values();

            $openBalance = round(
                (float) $lines->sum('debit') - (float) $lines->sum('credit'),
                2
            );

            $letterings = Lettering::withoutGlobalScopes()
                ->with(['lines.journalEntry:id,entry_date,journal_code,reference', 'contact:id,display_name', 'matcher:id,name'])
                ->where('company_id', $company->id)
                ->where('account_id', $account->id)
                ->when($contact, fn ($q) => $q->where('contact_id', $contact->id))
                ->orderByDesc('matched_at')
                ->limit(50)
                ->get()
                ->map(function (Lettering $l) {
                    return [
                        'id' => $l->id,
                        'code' => $l->code,
                        'match_type' => $l->match_type,
                        'total_amount' => (float) $l->total_amount,
                        'matched_at' => optional($l->matched_at)->toDateTimeString(),
                        'contact' => $l->contact ? [
                            'id' => $l->contact->id,
                            'display_name' => $l->contact->display_name,
                        ] : null,
                        'matcher' => $l->matcher ? $l->matcher->name : null,
                        'lines' => $l->lines->map(function ($ln) {
                            $e = $ln->journalEntry;

                            return [
                                'id' => $ln->id,
                                'entry_date' => optional($e?->entry_date)->toDateString() ?? optional($e)->entry_date,
                                'journal_code' => $e?->journal_code,
                                'reference' => $e?->reference,
                                'debit' => (float) $ln->debit,
                                'credit' => (float) $ln->credit,
                            ];
                        })->values(),
                    ];
                });
        }

        return Inertia::render('Ledger/Lettering', [
            'accounts' => $lettrableAccounts,
            'contacts' => $contacts,
            'selectedAccountId' => $selectedAccountId,
            'selectedContactId' => $selectedContactId,
            'account' => $account,
            'unletteredLines' => $unletteredLines,
            'letterings' => $letterings,
            'openBalance' => $openBalance,
        ]);
    }

    public function matchManual(Request $request): RedirectResponse
    {
        $company = app('currentCompany');

        $data = $request->validate([
            'account_id' => ['required', 'uuid'],
            'line_ids' => ['required', 'array', 'min:2'],
            'line_ids.*' => ['uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $data['account_id'])
            ->where('is_lettrable', true)
            ->firstOrFail();

        try {
            $lettering = $this->service->matchManual(
                $account,
                $data['line_ids'],
                $request->user(),
                'manual',
                $data['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('ledger.lettering', [
                'account_id' => $account->id,
                'contact_id' => $request->input('contact_id'),
            ])
            ->with('success', 'Lettrage '.$lettering->code.' créé.');
    }

    public function auto(Request $request): RedirectResponse
    {
        $company = app('currentCompany');

        $data = $request->validate([
            'account_id' => ['required', 'uuid'],
            'contact_id' => ['nullable', 'uuid'],
            'mode' => ['required', 'in:reference,amount'],
        ]);

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $data['account_id'])
            ->where('is_lettrable', true)
            ->firstOrFail();

        $contact = null;

        if (! empty($data['contact_id'])) {
            $contact = Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $data['contact_id'])
                ->first();
        }

        $result = $data['mode'] === 'reference'
            ? $this->service->autoMatchByReference($account, $request->user(), $contact)
            : $this->service->autoMatchByAmount($account, $request->user(), $contact);

        return redirect()
            ->route('ledger.lettering', [
                'account_id' => $account->id,
                'contact_id' => $data['contact_id'] ?? null,
            ])
            ->with('success', sprintf(
                'Lettrage automatique : %d groupes créés (%d lignes lettrées).',
                $result['groups'],
                $result['matched']
            ));
    }

    public function destroy(Lettering $lettering): RedirectResponse
    {
        abort_unless(
            $lettering->company_id === app('currentCompany')->id,
            403,
            'Accès non autorisé à ce lettrage.'
        );

        $accountId = $lettering->account_id;
        $contactId = $lettering->contact_id;
        $code = $lettering->code;

        $this->service->unmatch($lettering);

        return redirect()
            ->route('ledger.lettering', [
                'account_id' => $accountId,
                'contact_id' => $contactId,
            ])
            ->with('success', "Lettrage {$code} supprimé.");
    }
}
