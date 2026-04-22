<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $company = app('currentCompany');
        $metrics = new DashboardMetricsService($company);

        return Inertia::render('Dashboard/Index', [
            'kpis' => [
                ...$metrics->cachedKpis(),
            ],
            'charts' => [
                'invoiced_by_month' => $metrics->invoicedByMonth(),
                'collected_by_month' => $metrics->collectedByMonth(),
                'expenses_by_month' => $metrics->expensesByMonth(),
            ],
            'top_clients' => $metrics->topClientsByRevenue(),
            'recent_invoices' => $metrics->recentInvoices(),
        ]);
    }
}
