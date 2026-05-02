<?php

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function __construct(protected Company $company) {}

    public function invoicedThisMonth(): float
    {
        return (float) DB::table('invoices')
            ->where('company_id', $this->company->id)
            ->whereIn('status', ['issued', 'paid', 'partially_paid'])
            ->whereMonth('issue_date', now()->month)
            ->whereYear('issue_date', now()->year)
            ->sum('total_ttc');
    }

    public function collectedThisMonth(): float
    {
        return (float) DB::table('invoice_payments')
            ->where('company_id', $this->company->id)
            ->whereNull('deleted_at')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
    }

    public function outstandingReceivables(): float
    {
        $rows = DB::table('invoices')
            ->leftJoin('invoice_payments', function ($join) {
                $join->on('invoice_payments.invoice_id', '=', 'invoices.id')
                    ->whereNull('invoice_payments.deleted_at');
            })
            ->where('invoices.company_id', $this->company->id)
            ->whereIn('invoices.status', ['issued', 'partially_paid'])
            ->groupBy('invoices.id', 'invoices.total_ttc')
            ->selectRaw('GREATEST(invoices.total_ttc - COALESCE(SUM(invoice_payments.amount), 0), 0) AS remaining')
            ->get();

        return (float) $rows->sum('remaining');
    }

    public function outstandingPayables(): float
    {
        return (float) DB::table('expenses')
            ->where('company_id', $this->company->id)
            ->where('status', 'confirmed')
            ->sum('total_ttc');
    }

    public function overdueInvoicesCount(): int
    {
        return DB::table('invoices')
            ->leftJoin('invoice_payments', function ($join) {
                $join->on('invoice_payments.invoice_id', '=', 'invoices.id')
                    ->whereNull('invoice_payments.deleted_at');
            })
            ->select('invoices.id')
            ->where('invoices.company_id', $this->company->id)
            ->whereIn('invoices.status', ['issued', 'partially_paid'])
            ->whereDate('invoices.due_date', '<', now()->toDateString())
            ->groupBy('invoices.id', 'invoices.total_ttc')
            ->havingRaw('COALESCE(SUM(invoice_payments.amount), 0) < invoices.total_ttc')
            ->get()
            ->count();
    }

    public function overdueInvoicesAmount(): float
    {
        $rows = DB::table('invoices')
            ->leftJoin('invoice_payments', function ($join) {
                $join->on('invoice_payments.invoice_id', '=', 'invoices.id')
                    ->whereNull('invoice_payments.deleted_at');
            })
            ->where('invoices.company_id', $this->company->id)
            ->whereIn('invoices.status', ['issued', 'partially_paid'])
            ->whereDate('invoices.due_date', '<', now()->toDateString())
            ->groupBy('invoices.id', 'invoices.total_ttc')
            ->havingRaw('COALESCE(SUM(invoice_payments.amount), 0) < invoices.total_ttc')
            ->selectRaw('GREATEST(invoices.total_ttc - COALESCE(SUM(invoice_payments.amount), 0), 0) AS remaining')
            ->get();

        return (float) $rows->sum('remaining');
    }

    public function invoicedByMonth(): array
    {
        $rows = DB::table('invoices')
            ->selectRaw("to_char(issue_date, 'YYYY-MM') as month, SUM(total_ttc) as total")
            ->where('company_id', $this->company->id)
            ->whereIn('status', ['issued', 'paid', 'partially_paid'])
            ->where('issue_date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->groupByRaw("to_char(issue_date, 'YYYY-MM')")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return $this->fillMonthGaps($rows, 'total');
    }

    public function collectedByMonth(): array
    {
        $rows = DB::table('invoice_payments')
            ->selectRaw("to_char(date, 'YYYY-MM') as month, SUM(amount) as total")
            ->where('company_id', $this->company->id)
            ->whereNull('deleted_at')
            ->where('date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->groupByRaw("to_char(date, 'YYYY-MM')")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return $this->fillMonthGaps($rows, 'total');
    }

    public function expensesByMonth(): array
    {
        $rows = DB::table('expenses')
            ->selectRaw("to_char(expense_date, 'YYYY-MM') as month, SUM(total_ttc) as total")
            ->where('company_id', $this->company->id)
            ->where('status', 'confirmed')
            ->where('expense_date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->groupByRaw("to_char(expense_date, 'YYYY-MM')")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return $this->fillMonthGaps($rows, 'total');
    }

    public function topClientsByRevenue(int $limit = 5): array
    {
        return DB::table('invoices')
            ->join('contacts', 'contacts.id', '=', 'invoices.contact_id')
            ->selectRaw('contacts.id, contacts.display_name as name, SUM(invoices.total_ttc) as total_revenue, COUNT(invoices.id) as invoice_count')
            ->where('invoices.company_id', $this->company->id)
            ->whereIn('invoices.status', ['issued', 'paid', 'partially_paid'])
            ->whereYear('invoices.issue_date', now()->year)
            ->groupBy('contacts.id', 'contacts.display_name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'total_revenue' => (float) $row->total_revenue,
                'invoice_count' => (int) $row->invoice_count,
            ])
            ->toArray();
    }

    public function recentInvoices(int $limit = 5): array
    {
        $rows = DB::table('invoices')
            ->leftJoin('contacts', 'contacts.id', '=', 'invoices.contact_id')
            ->leftJoin('invoice_payments', function ($join) {
                $join->on('invoice_payments.invoice_id', '=', 'invoices.id')
                    ->whereNull('invoice_payments.deleted_at');
            })
            ->selectRaw('
                invoices.id,
                invoices.invoice_number as number,
                invoices.total_ttc as total,
                invoices.status,
                invoices.due_date,
                invoices.issue_date,
                contacts.display_name as client_name,
                COALESCE(SUM(invoice_payments.amount), 0) as total_paid
            ')
            ->where('invoices.company_id', $this->company->id)
            ->whereIn('invoices.status', ['issued', 'paid', 'partially_paid'])
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.total_ttc',
                'invoices.status',
                'invoices.due_date',
                'invoices.issue_date',
                'contacts.display_name'
            )
            ->orderByDesc('invoices.issue_date')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $total = (float) $row->total;
            $totalPaid = (float) $row->total_paid;
            $remaining = $total - $totalPaid;
            $paymentStatus = 'unpaid';

            if ($remaining <= 0.00001) {
                $paymentStatus = 'paid';
            } elseif ($totalPaid > 0.00001) {
                $paymentStatus = 'partially_paid';
            } elseif ($row->due_date && Carbon::parse($row->due_date)->isPast()) {
                $paymentStatus = 'overdue';
            }

            return [
                'id' => $row->id,
                'number' => $row->number,
                'total' => $total,
                'payment_status' => $paymentStatus,
                'issue_date' => $row->issue_date,
                'client_name' => $row->client_name ?? '—',
            ];
        })->toArray();
    }

    public function cachedKpis(): array
    {
        $key = sprintf('dashboard_kpis_%s_%s', $this->company->id, now()->format('Y-m'));

        return Cache::remember($key, 300, function () {
            return [
                'invoiced_this_month' => $this->invoicedThisMonth(),
                'collected_this_month' => $this->collectedThisMonth(),
                'outstanding_receivables' => $this->outstandingReceivables(),
                'outstanding_payables' => $this->outstandingPayables(),
                'overdue_count' => $this->overdueInvoicesCount(),
                'overdue_amount' => $this->overdueInvoicesAmount(),
            ];
        });
    }

    private function fillMonthGaps($rows, string $field): array
    {
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');
            $result[] = [
                'month' => $month,
                'label' => $date->translatedFormat('M Y'),
                'value' => isset($rows[$month]) ? (float) $rows[$month]->$field : 0.0,
            ];
        }

        return $result;
    }
}
