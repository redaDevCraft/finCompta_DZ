<?php

namespace App\Http\Controllers;

use App\Http\Resources\BankTransactionListResource;
use App\Http\Resources\JournalEntryListResource;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalEntry;
use App\Services\ReconciliationService;
use App\Support\ListQuery\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = app('currentCompany')->id;

        // Two cursor paginators live on the same URL, so each uses a distinct
        // cursor query key (tx_cursor vs oi_cursor). Otherwise navigating one
        // side would reset the other. The ordering tuples used below are
        // strictly monotonic (id tiebreaker), which is the precondition
        // cursorPaginate needs.
        $bankTransactions = BankTransaction::query()
            ->where('reconcile_status', 'unmatched')
            ->with('bankAccount:id,bank_name,account_number')
            ->select([
                'id',
                'company_id',
                'bank_account_id',
                'transaction_date',
                'value_date',
                'label',
                'amount',
                'direction',
                'balance_after',
                'reconcile_status',
            ])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->cursorPaginate(PerPage::resolve($request, default: 50, max: 100), cursorName: 'tx_cursor')
            ->withQueryString()
            ->through(fn (BankTransaction $tx) => (new BankTransactionListResource($tx))->toArray($request));

        $openItems = JournalEntry::query()
            ->where('status', 'posted')
            ->whereDoesntHave('bankTransaction')
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->with('period:id,year,month,status')
            ->select([
                'id',
                'company_id',
                'period_id',
                'entry_date',
                'journal_code',
                'reference',
                'description',
                'status',
                'source_type',
                'source_id',
                'posted_at',
                'created_at',
            ])
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(PerPage::resolve($request, default: 50, max: 100), cursorName: 'oi_cursor')
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => (new JournalEntryListResource($entry))->toArray($request));

        $bankAccounts = BankAccount::query()
            ->where('company_id', $companyId)
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_number', 'gl_account_id']);

        // Posting accounts no longer shipped here. The Bank/Reconcile manual-
        // post modal now uses /suggest/accounts via AsyncCombobox, so a large
        // chart of accounts doesn't have to be inlined on every page load.
        return Inertia::render('Bank/Reconcile', [
            'bankTransactions' => $bankTransactions,
            'openItems' => $openItems,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function match(Request $request, ReconciliationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'bank_transaction_id' => ['required', 'uuid'],
            'journal_entry_id' => ['required', 'uuid'],
        ]);

        $companyId = app('currentCompany')->id;

        $tx = BankTransaction::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['bank_transaction_id']);

        $entry = JournalEntry::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['journal_entry_id']);

        $service->confirmMatch($tx, $entry, $user = $request->user());

        return back()->with('success', 'Rapprochement confirmé avec succès.');
    }

    public function exclude(Request $request, ReconciliationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'bank_transaction_id' => ['required', 'uuid'],
        ]);

        $companyId = app('currentCompany')->id;

        $tx = BankTransaction::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['bank_transaction_id']);

        $service->exclude($tx);

        return back()->with('success', 'Transaction exclue du rapprochement.');
    }

    public function manualPost(Request $request, ReconciliationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'bank_transaction_id' => ['required', 'uuid'],
            'account_id' => ['required', 'uuid'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $companyId = app('currentCompany')->id;

        $tx = BankTransaction::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['bank_transaction_id']);

        Account::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['account_id']);

        $service->manualPost(
            $tx,
            $validated['account_id'],
            $validated['description'],
            $user = $request->user(),
        );

        return back()->with('success', 'Écriture manuelle créée avec succès.');
    }
}