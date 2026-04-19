<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminDashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/Dashboard', [
            'pendingPayments' => Payment::query()
                ->whereIn('status', ['pending', 'processing'])
                ->count(),
            'recentPayments' => Payment::query()
                ->with(['company:id,raison_sociale', 'plan:id,name,code'])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(['id', 'company_id', 'plan_id', 'reference', 'gateway', 'method', 'status', 'amount_dzd', 'currency', 'created_at']),
        ]);
    }
}
