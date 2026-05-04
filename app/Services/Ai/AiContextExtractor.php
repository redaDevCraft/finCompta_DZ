<?php

namespace App\Services\Ai;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Quote;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Cache;

class AiContextExtractor
{
    /**
     * Build a rich, company-wide context snapshot for the AI.
     * Aggregates invoices, expenses, clients, suppliers, quotes, banking, ledger and VAT.
     */
    public function extract(Company $company): array
    {
        $key = "ai_context_full.{$company->id}." . now()->format('YmdH');

        return Cache::remember($key, now()->addHour(), function () use ($company) {
            return [
                'overview'   => $this->overview($company),
                'invoices'   => $this->invoices($company),
                'expenses'   => $this->expenses($company),
                'clients'    => $this->clients($company),
                'suppliers'  => $this->suppliers($company),
                'quotes'     => $this->quotes($company),
                'banking'    => $this->banking($company),
                'ledger'     => $this->ledger($company),
                'vat'        => $this->vat($company),
            ];
        });
    }

    protected function overview(Company $company): array
    {
        // Global picture: revenue vs expenses this year + counts
        $yearStart = now()->copy()->startOfYear();

        $issuedInvoices = Invoice::query()
            ->where('company_id', $company->id)
            ->issued() // status in issued/partially_paid/paid[cite:172]
            ->whereDate('issue_date', '>=', $yearStart);

        $confirmedExpenses = Expense::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['confirmed', 'paid']) // from Expense scopes/usage[cite:173]
            ->whereDate('expense_date', '>=', $yearStart);

        return [
            'period' => [
                'from' => $yearStart->toDateString(),
                'to'   => now()->toDateString(),
            ],
            'revenue_year_to_date' => [
                'count'     => (clone $issuedInvoices)->count(),
                'total_ht'  => (clone $issuedInvoices)->sum('subtotal_ht'),
                'total_ttc' => (clone $issuedInvoices)->sum('total_ttc'),
            ],
            'expenses_year_to_date' => [
                'count' => (clone $confirmedExpenses)->count(),
                'total' => (clone $confirmedExpenses)->sum('total_ttc'),
            ],
            'net_result_approx' => (clone $issuedInvoices)->sum('total_ttc')
                - (clone $confirmedExpenses)->sum('total_ttc'),
        ];
    }

    protected function invoices(Company $company): array
    {
        $base = Invoice::query()
            ->where('company_id', $company->id); // global scope also enforces[cite:172]

        $thisMonth = (clone $base)
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year);

        $issued = (clone $base)->issued();

        $overdue = (clone $issued)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());

        return [
            'this_month' => [
                'count'     => (clone $thisMonth)->count(),
                'total_ht'  => (clone $thisMonth)->sum('subtotal_ht'),
                'total_ttc' => (clone $thisMonth)->sum('total_ttc'),
            ],
            'by_status' => [
                'draft'          => (clone $base)->where('status', 'draft')->count(),
                'issued'         => (clone $base)->where('status', 'issued')->count(),
                'partially_paid' => (clone $base)->where('status', 'partially_paid')->count(),
                'paid'           => (clone $base)->where('status', 'paid')->count(),
                'voided'         => (clone $base)->where('status', 'voided')->count(),
            ],
            'overdue' => [
                'count'       => (clone $overdue)->count(),
                'total_ttc'   => (clone $overdue)->sum('total_ttc'),
            ],
        ];
    }

    protected function expenses(Company $company): array
    {
        $base = Expense::query()
            ->where('company_id', $company->id); // company global scope also applies[cite:173]

        $thisMonth = (clone $base)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year);

        $last3Months = (clone $base)
            ->whereDate('expense_date', '>=', now()->copy()->subMonths(3)->startOfMonth());

        return [
            'this_month' => [
                'count' => (clone $thisMonth)->count(),
                'total' => (clone $thisMonth)->sum('total_ttc'),
            ],
            'last_3_months' => [
                'count' => (clone $last3Months)->count(),
                'total' => (clone $last3Months)->sum('total_ttc'),
            ],
            'by_status' => [
                'draft'     => (clone $base)->where('status', 'draft')->count(),
                'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
                'paid'      => (clone $base)->where('status', 'paid')->count(),
            ],
        ];
    }

    protected function clients(Company $company): array
    {
        // use Contact scopes: clients(), invoices(), expenses()[cite:174]
        $clients = Contact::query()
            ->forCompany($company->id)
            ->clients()
            ->withCount('invoices')
            ->withSum('invoices as total_billed', 'total_ttc')
            ->orderByDesc('total_billed')
            ->limit(10)
            ->get()
            ->map(function (Contact $c) {
                return [
                    'label'         => $c->display_name ?? $c->raison_sociale ?? 'Client',
                    'invoices_count'=> $c->invoices_count,
                    'total_billed'  => $c->total_billed,
                ];
            });

        return [
            'count'        => Contact::forCompany($company->id)->clients()->count(),
            'top_clients'  => $clients,
        ];
    }

    protected function suppliers(Company $company): array
    {
        $suppliers = Contact::query()
            ->forCompany($company->id)
            ->suppliers()
            ->withCount('expenses')
            ->withSum('expenses as total_expenses', 'total_ttc')
            ->orderByDesc('total_expenses')
            ->limit(10)
            ->get()
            ->map(function (Contact $c) {
                return [
                    'label'           => $c->display_name ?? $c->raison_sociale ?? 'Fournisseur',
                    'expenses_count'  => $c->expenses_count,
                    'total_expenses'  => $c->total_expenses,
                ];
            });

        return [
            'count'          => Contact::forCompany($company->id)->suppliers()->count(),
            'top_suppliers'  => $suppliers,
        ];
    }

    protected function quotes(Company $company): array
    {
        $base = Quote::query()
            ->where('company_id', $company->id); // company global scope also applies[cite:179]

        $open = (clone $base)->whereIn('status', ['draft', 'sent']);

        return [
            'total' => [
                'count' => (clone $base)->count(),
                'sum'   => (clone $base)->sum('total'),
            ],
            'open' => [
                'count' => (clone $open)->count(),
                'sum'   => (clone $open)->sum('total'),
            ],
            'converted_to_invoice' => [
                'count' => (clone $base)->whereNotNull('invoice_id')->count(),
            ],
        ];
    }

    protected function banking(Company $company): array
    {
        $accounts = BankAccount::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get(); // Has transactions() and bank_name, account_number[cite:175]

        $accountSummaries = $accounts->map(function (BankAccount $account) {
            $last = $account->transactions()
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->first();

            return [
                'label'   => trim(($account->bank_name ?? 'Compte') . ' ' . substr($account->account_number ?? '', -4)),
                'balance' => $last?->balance_after ?? 0,
            ];
        });

        $recentTransactions = BankTransaction::query()
            ->forCompany($company->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (BankTransaction $t) => [
                'date'   => optional($t->transaction_date)->format('Y-m-d'),
                'label'  => $t->label,
                'amount' => $t->amount,
                'direction' => $t->direction,
            ]);

        $unmatchedCount = BankTransaction::query()
            ->forCompany($company->id)
            ->unmatched()
            ->count(); // reconcile_status = unmatched[cite:176]

        return [
            'accounts'            => $accountSummaries,
            'recent_transactions' => $recentTransactions,
            'unmatched_transactions_count' => $unmatchedCount,
        ];
    }

    protected function ledger(Company $company): array
    {
        $entries = JournalEntry::query()
            ->forCompany($company->id); // global scope also applies[cite:177]

        $posted = (clone $entries)->posted();

        $lastEntries = (clone $posted)
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (JournalEntry $e) {
                return [
                    'entry_date' => optional($e->entry_date)->format('Y-m-d'),
                    'journal_code' => $e->journal_code,
                    'status'       => $e->status,
                    'description'  => $e->description,
                    'is_balanced'  => $e->isBalanced(),
                ];
            });

        return [
            'total_entries'  => (clone $entries)->count(),
            'posted_entries' => (clone $posted)->count(),
            'latest_posted_entries' => $lastEntries,
        ];
    }

    protected function vat(Company $company): array
    {
        $yearStart = now()->copy()->startOfYear();

        $vatOnInvoices = Invoice::query()
            ->where('company_id', $company->id)
            ->issued()
            ->whereDate('issue_date', '>=', $yearStart)
            ->sum('total_vat'); // total_vat casted decimal:2[cite:172]

        $activeRates = TaxRate::query()
            ->forCompany($company->id)
            ->active()
            ->orderBy('rate_percent')
            ->get()
            ->map(fn (TaxRate $rate) => [
                'label'        => $rate->label,
                'rate_percent' => $rate->rate_percent,
                'tax_type'     => $rate->tax_type,
                'is_recoverable' => $rate->is_recoverable,
            ]);

        return [
            'vat_collected_year_to_date' => $vatOnInvoices,
            'active_rates'               => $activeRates,
        ];
    }
    }