<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app('currentCompany');

        // Recent invoices (last 30 days)
        $recentInvoices = Invoice::where('company_id', $company->id)
            ->where('issue_date', '>=', now()->subDays(30))
            ->with('contact')
            ->latest('issue_date')
            ->limit(5)
            ->get();

        // Stats
        $stats = [
            'total_invoices' => Invoice::where('company_id', $company->id)->count(),
            'unpaid_invoices' => Invoice::where('company_id', $company->id)
                ->issued()
                ->unpaid()
                ->sum('total_ttc'),
            'total_expenses' => Expense::where('company_id', $company->id)
                ->whereIn('status', ['confirmed', 'posted'])
                ->sum('total_ttc'),
            'vat_due' => 0, // Computed from VAT buckets or reports
            'recent_expenses_count' => Expense::where('company_id', $company->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];

        // Quick charts data (last 7 days)
        $dailyRevenue = Invoice::where('company_id', $company->id)
            ->issued()
            ->whereBetween('issue_date', [now()->subDays(7), now()])
            ->selectRaw('DATE(issue_date) as date, SUM(total_ttc) as amount')
            ->groupBy('date')
            ->pluck('amount', 'date')
            ->toArray();

        return Inertia::render('Dashboard', compact(
            'stats',
            'recentInvoices',
            'dailyRevenue'
        ));
    }
}

