<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = app('currentCompany')->id;
        $type = $request->string('type')->toString();

        $query = Contact::query()
            ->where('company_id', $companyId)
            ->orderBy('display_name');

        if (in_array($type, ['client', 'supplier', 'both'], true)) {
            $query->where('type', $type);
        }

        $contacts = $query
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Contacts/index', [
            'contacts' => $contacts,
            'filters' => [
                'type' => $type ?: null,
            ],
        ]);
    }

    public function show(Contact $contact): Response
    {
        $this->assertOwnership($contact);

        $invoices = Invoice::query()
            ->where('company_id', $contact->company_id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('issue_date')
            ->limit(25)
            ->get([
                'id', 'invoice_number', 'issue_date', 'due_date',
                'total_ttc', 'status', 'document_type', 'currency',
            ]);

        $expenses = Expense::query()
            ->where('company_id', $contact->company_id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('expense_date')
            ->limit(25)
            ->get([
                'id', 'reference', 'expense_date', 'due_date',
                'total_ttc', 'status', 'description',
            ]);

        $prefix = match ($contact->type) {
            'supplier' => '401',
            'both' => null,
            default => '411',
        };

        $balances = $this->partyBalances($contact, $prefix);

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'invoices' => $invoices,
            'expenses' => $expenses,
            'balances' => $balances,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = app('currentCompany')->id;

        $validated = $request->validate($this->rules());

        Contact::query()->create([
            ...$validated,
            'company_id' => $companyId,
        ]);

        return back()->with('success', 'Contact créé avec succès.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->assertOwnership($contact);

        $validated = $request->validate($this->rules());

        $contact->update($validated);

        return back()->with('success', 'Contact mis à jour avec succès.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->assertOwnership($contact);

        $hasActiveInvoices = $contact->invoices()
            ->whereNotIn('status', ['void'])
            ->exists();

        $hasActiveExpenses = $contact->expenses()
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($hasActiveInvoices || $hasActiveExpenses) {
            return back()->withErrors([
                'delete' => 'Impossible de supprimer ce contact car il est référencé par des factures ou des dépenses actives.',
            ]);
        }

        $contact->delete();

        return back()->with('success', 'Contact supprimé avec succès.');
    }

    protected function assertOwnership(Contact $contact): void
    {
        abort_unless(
            $contact->company_id === app('currentCompany')->id,
            404
        );
    }

    /**
     * Aggregate open receivable / payable balance for this contact.
     * If $prefix is null, returns both 411 (client) and 401 (supplier).
     *
     * @return array<string, array{open: float, debit: float, credit: float}>
     */
    protected function partyBalances(Contact $contact, ?string $prefix): array
    {
        $prefixes = $prefix ? [$prefix] : ['411', '401'];

        $out = [];

        foreach ($prefixes as $p) {
            $row = JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.company_id', $contact->company_id)
                ->where('journal_entries.status', 'posted')
                ->where('journal_lines.contact_id', $contact->id)
                ->where('accounts.code', 'like', $p.'%')
                ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
                ->first();

            $debit = (float) ($row->debit ?? 0);
            $credit = (float) ($row->credit ?? 0);

            $out[$p] = [
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'open' => round($debit - $credit, 2),
            ];
        }

        return $out;
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:client,supplier,both'],
            'entity_type' => ['required', 'in:individual,enterprise'],
            'display_name' => ['required', 'string', 'max:255'],
            'raison_sociale' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'regex:/^\d{15}$/'],
            'nis' => ['nullable', 'regex:/^\d{15}$/'],
            'rc' => ['nullable', 'regex:/^\d{2}\/\d{2}-\d{7}\sB\s\d{2}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'regex:/^(05|06|07)\d{8}$/'],
            'default_payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'default_payment_mode' => ['nullable', 'in:Virement bancaire,Chèque,Espèces,Effet de commerce,Carte bancaire,Chargily (E-paiement),Slickpay,Autre'],
            'default_expense_account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'default_tax_rate_id' => ['nullable', 'uuid', 'exists:tax_rates,id'],
            'address_line1' => ['nullable', 'string', 'max:500'],
            'address_wilaya' => ['nullable', 'string', 'max:100'],
        ];
    }
}
