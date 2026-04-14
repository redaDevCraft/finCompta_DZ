<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalEntry;
use App\Services\ReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(): Response
{
    $companyId = app('currentCompany')->id;

    $bankTransactions = BankTransaction::query()
        ->where('company_id', $companyId)
        ->where('reconcile_status', 'unmatched')
        ->with('bankAccount')
        ->orderByDesc('transaction_date')
        ->paginate(50)
        ->withQueryString();

    $openItems = JournalEntry::query()
        ->where('company_id', $companyId)
        ->where('status', 'posted')
        ->whereDoesntHave('bankTransaction')
        ->with('lines.account')
        ->orderByDesc('entry_date')
        ->get();

    $bankAccounts = BankAccount::query()
        ->where('company_id', $companyId)
        ->orderBy('bank_name')
        ->get(['id', 'bank_name', 'account_number', 'gl_account_id']);

    $postingAccounts = Account::query()
        ->where('company_id', $companyId)
        ->where('is_active', true)
        ->orderBy('code')
        ->get(['id', 'code', 'label']);

    return Inertia::render('Bank/Reconcile', [
        'bankTransactions' => $bankTransactions,
        'openItems' => $openItems,
        'bankAccounts' => $bankAccounts,
        'postingAccounts' => $postingAccounts,
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