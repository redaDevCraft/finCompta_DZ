<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $companyId = app('currentCompany')->id;
        $startOfMonth = now()->startOfMonth();

        $revenueMtd = Invoice::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['issued', 'paid'])
            ->whereDate('issue_date', '>=', $startOfMonth)
            ->sum('total_ttc');

        $expensesMtd = Expense::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['confirmed', 'paid'])
            ->whereDate('expense_date', '>=', $startOfMonth)
            ->sum('total_ttc');

        $arTotal = Invoice::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->sum('total_ttc');

        $apTotal = Expense::query()
            ->where('company_id', $companyId)
            ->where('status', 'confirmed')
            ->sum('total_ttc');

        $recentInvoices = Invoice::query()
            ->where('company_id', $companyId)
            ->with('contact')
            ->orderByDesc('issue_date')
            ->limit(5)
            ->get();

        $unmatchedBankCount = BankTransaction::query()
            ->where('company_id', $companyId)
            ->where('reconcile_status', 'unmatched')
            ->count();

        $pendingDocumentsCount = Document::query()
            ->where('company_id', $companyId)
            ->whereIn('ocr_status', ['processing', 'pending'])
            ->count();

        return Inertia::render('Dashboard/Index', [
            'revenue_mtd' => (float) $revenueMtd,
            'expenses_mtd' => (float) $expensesMtd,
            'ar_total' => (float) $arTotal,
            'ap_total' => (float) $apTotal,
            'recent_invoices' => $recentInvoices,
            'unmatched_bank_count' => $unmatchedBankCount,
            'pending_documents_count' => $pendingDocumentsCount,
        ]);
    }
}