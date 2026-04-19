<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $companyId = app('currentCompany')->id;
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $startOfYear = $today->copy()->startOfYear();

        // --- KPIs from general ledger (posted entries) ---
        // Class 5 = Cash & bank
        $cashBalance = $this->sumClassBalance($companyId, [5]);
        // Class 411 = Clients (AR)
        $arBalance = $this->sumAccountBalanceByCodePrefix($companyId, ['411']);
        // Class 401 = Suppliers (AP) — normally credit balance, we return absolute
        $apBalance = $this->sumAccountBalanceByCodePrefix($companyId, ['401']);

        // Revenue YTD (class 7 = credit normal, balance = credit - debit)
        $revenueYtd = $this->sumClassRevenue($companyId, 7, $startOfYear, $today);
        $revenueMtd = $this->sumClassRevenue($companyId, 7, $startOfMonth, $today);

        // Expenses YTD / MTD (class 6 = debit normal)
        $expensesYtd = $this->sumClassExpense($companyId, 6, $startOfYear, $today);
        $expensesMtd = $this->sumClassExpense($companyId, 6, $startOfMonth, $today);

        $resultYtd = $revenueYtd - $expensesYtd;

        // --- Monthly revenue vs expenses series (12 months rolling) ---
        $series = $this->buildMonthlySeries($companyId, $today);

        // --- Top 5 open client balances (Classe 411 unlettered) ---
        $topDebtors = $this->topOpenBalances($companyId, '411', direction: 'debit');
        $topCreditors = $this->topOpenBalances($companyId, '401', direction: 'credit');

        // --- Recent posted journal entries ---
        $recentEntries = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->with('journal:id,code,label')
            ->withSum('lines as total_debit', 'debit')
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // --- Operational signals ---
        $draftEntriesCount = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->count();

        $unmatchedBankCount = BankTransaction::query()
            ->where('company_id', $companyId)
            ->where('reconcile_status', 'unmatched')
            ->count();

        $pendingDocumentsCount = Document::query()
            ->where('company_id', $companyId)
            ->whereIn('ocr_status', ['processing', 'pending'])
            ->count();

        $unletteredLinesCount = $this->unletteredLinesCount($companyId);

        // --- Invoices / Expenses fallbacks (still useful when user creates them) ---
        $draftInvoicesCount = Invoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->count();

        $draftExpensesCount = Expense::query()
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->count();

        $recentInvoices = Invoice::query()
            ->where('company_id', $companyId)
            ->with('contact:id,display_name')
            ->orderByDesc('issue_date')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'kpis' => [
                'cash_balance' => (float) $cashBalance,
                'ar_balance' => (float) $arBalance,
                'ap_balance' => (float) $apBalance,
                'revenue_ytd' => (float) $revenueYtd,
                'revenue_mtd' => (float) $revenueMtd,
                'expenses_ytd' => (float) $expensesYtd,
                'expenses_mtd' => (float) $expensesMtd,
                'result_ytd' => (float) $resultYtd,
            ],
            'series' => $series,
            'top_debtors' => $topDebtors,
            'top_creditors' => $topCreditors,
            'recent_entries' => $recentEntries,
            'recent_invoices' => $recentInvoices,
            'signals' => [
                'draft_entries' => $draftEntriesCount,
                'draft_invoices' => $draftInvoicesCount,
                'draft_expenses' => $draftExpensesCount,
                'unmatched_bank' => $unmatchedBankCount,
                'pending_documents' => $pendingDocumentsCount,
                'unlettered_lines' => $unletteredLinesCount,
            ],
        ]);
    }

    /**
     * Sum of debit-credit over all posted lines for accounts matching a class.
     */
    private function sumClassBalance(string $companyId, array $classes): float
    {
        $result = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereIn('accounts.class', $classes)
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as balance')
            ->value('balance');

        return (float) ($result ?? 0);
    }

    private function sumAccountBalanceByCodePrefix(string $companyId, array $prefixes): float
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted');

        $query->where(function ($q) use ($prefixes) {
            foreach ($prefixes as $p) {
                $q->orWhere('accounts.code', 'like', $p.'%');
            }
        });

        $value = $query->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as balance')
            ->value('balance');

        return (float) abs($value ?? 0);
    }

    private function sumClassRevenue(string $companyId, int $class, Carbon $from, Carbon $to): float
    {
        $value = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('accounts.class', $class)
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(journal_lines.credit - journal_lines.debit), 0) as total')
            ->value('total');

        return (float) ($value ?? 0);
    }

    private function sumClassExpense(string $companyId, int $class, Carbon $from, Carbon $to): float
    {
        $value = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('accounts.class', $class)
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as total')
            ->value('total');

        return (float) ($value ?? 0);
    }

    /**
     * Return [ ['month'=>'2026-01', 'revenue'=>.., 'expense'=>..], ... ] for last 12 months.
     */
    private function buildMonthlySeries(string $companyId, Carbon $today): array
    {
        $start = $today->copy()->startOfMonth()->subMonths(11);
        $end = $today->copy()->endOfMonth();

        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereIn('accounts.class', [6, 7])
            ->whereBetween('journal_entries.entry_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("to_char(journal_entries.entry_date, 'YYYY-MM') as month, accounts.class as class, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit")
            ->groupBy('month', 'accounts.class')
            ->orderBy('month')
            ->get();

        $series = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $series[$cursor->format('Y-m')] = [
                'month' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('M Y'),
                'revenue' => 0.0,
                'expense' => 0.0,
            ];
            $cursor->addMonth();
        }

        foreach ($rows as $r) {
            if (! isset($series[$r->month])) {
                continue;
            }
            if ((int) $r->class === 7) {
                $series[$r->month]['revenue'] += (float) $r->credit - (float) $r->debit;
            } else {
                $series[$r->month]['expense'] += (float) $r->debit - (float) $r->credit;
            }
        }

        return array_values($series);
    }

    /**
     * Top open balances per contact for a given account code prefix.
     * $direction 'debit' => positive balance (clients), 'credit' => positive credit balance (suppliers).
     */
    private function topOpenBalances(string $companyId, string $prefix, string $direction): array
    {
        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'journal_lines.contact_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('accounts.code', 'like', $prefix.'%')
            ->whereNull('journal_lines.lettering_id')
            ->whereNotNull('journal_lines.contact_id')
            ->selectRaw('journal_lines.contact_id, MAX(contacts.display_name) as display_name, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->groupBy('journal_lines.contact_id')
            ->get();

        $balanced = $rows->map(function ($r) use ($direction) {
            $debit = (float) $r->debit;
            $credit = (float) $r->credit;
            $balance = $direction === 'debit' ? $debit - $credit : $credit - $debit;

            return [
                'contact_id' => $r->contact_id,
                'display_name' => $r->display_name ?? '—',
                'balance' => round($balance, 2),
            ];
        })
            ->filter(fn ($r) => $r['balance'] > 0.009)
            ->sortByDesc('balance')
            ->take(5)
            ->values()
            ->all();

        return $balanced;
    }

    private function unletteredLinesCount(string $companyId): int
    {
        return (int) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('accounts.is_lettrable', true)
            ->whereNull('journal_lines.lettering_id')
            ->count();
    }
}
