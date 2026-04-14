<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\TaxRate;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $company = app('currentCompany');

        $expenses = Expense::query()
            ->where('company_id', $company->id)
            ->with(['contact', 'account'])
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
        ]);
    }

    public function create(): Response
    {
        $company = app('currentCompany');

        $contacts = Contact::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('type', ['supplier', 'both'])
            ->orderBy('display_name')
            ->get();

        $taxRates = TaxRate::query()
            ->where(function ($query) use ($company) {
                $query->where('company_id', $company->id)
                    ->orWhereNull('company_id');
            })
            ->where('is_active', true)
            ->orderBy('rate_percent')
            ->get();

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('class', 6)
            ->orderBy('code')
            ->get();

        return Inertia::render('Expenses/Create', [
            'contacts' => $contacts,
            'taxRates' => $taxRates,
            'accounts' => $accounts,
        ]);
    }

    public function store(\Illuminate\Http\Request $request): RedirectResponse
    {
        $company = app('currentCompany');

        $validated = $request->validate([
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'reference' => 'nullable|string|max:100',
            'expense_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:expense_date',
            'description' => 'nullable|string|max:2000',
            'total_ht' => 'required|numeric|min:0',
            'total_vat' => 'nullable|numeric|min:0',
            'total_ttc' => 'required|numeric|min:0',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'source_document_id' => 'nullable|uuid',
        ]);

        $expense = Expense::create([
            'company_id' => $company->id,
            'contact_id' => $validated['contact_id'] ?? null,
            'supplier_snapshot' => null,
            'reference' => $validated['reference'] ?? null,
            'expense_date' => $validated['expense_date'],
            'due_date' => $validated['due_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'total_ht' => $validated['total_ht'],
            'total_vat' => $validated['total_vat'] ?? 0,
            'total_ttc' => $validated['total_ttc'],
            'account_id' => $validated['account_id'] ?? null,
            'status' => 'draft',
            'source_document_id' => $validated['source_document_id'] ?? null,
            'ai_extracted' => false,
            'journal_entry_id' => null,
        ]);

        return redirect()->route('expenses.show', $expense);
    }

    public function show(Expense $expense): Response
    {
        $this->authorizeExpense($expense);

        $expense->load([
            'contact',
            'account',
            'document',
            'journalEntry.lines.account',
        ]);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense,
        ]);
    }

    public function update(\Illuminate\Http\Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeExpense($expense);

        abort_if(! $expense->isEditable(), 422, 'Dépense déjà confirmée, non modifiable');

        $validated = $request->validate([
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'reference' => 'nullable|string|max:100',
            'expense_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:expense_date',
            'description' => 'nullable|string|max:2000',
            'total_ht' => 'required|numeric|min:0',
            'total_vat' => 'nullable|numeric|min:0',
            'total_ttc' => 'required|numeric|min:0',
            'account_id' => 'nullable|uuid|exists:accounts,id',
        ]);

        $expense->update([
            'contact_id' => $validated['contact_id'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'expense_date' => $validated['expense_date'],
            'due_date' => $validated['due_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'total_ht' => $validated['total_ht'],
            'total_vat' => $validated['total_vat'] ?? 0,
            'total_ttc' => $validated['total_ttc'],
            'account_id' => $validated['account_id'] ?? null,
        ]);

        return back()->with('success', 'Dépense mise à jour');
    }

    public function confirm(Expense $expense, ExpenseService $service): RedirectResponse
    {
        $this->authorizeExpense($expense);
    
        $user = request()->user();
    
        abort_if(! $user, 403, 'Utilisateur non authentifié');
    
        $result = $service->confirm($expense, app('currentCompany'), $user);
    
        return redirect()
            ->route('expenses.show', $expense)
            ->with('warnings', $result['warnings'] ?? [])
            ->with('success', 'Dépense confirmée');
    }

    private function authorizeExpense(Expense $expense): void
    {
        abort_unless($expense->company_id === app('currentCompany')->id, 403, 'Accès non autorisé à cette dépense');
    }
}