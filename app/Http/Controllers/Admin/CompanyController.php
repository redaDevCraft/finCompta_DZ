<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CompanyController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->input('search', ''));

        $companies = Company::query()
            ->with([
                'subscription.plan:id,code,name',
            ])
            ->withCount([
                'users as users_count',
                'invoices as invoices_count',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('raison_sociale', 'ilike', "%{$search}%")
                        ->orWhere('nif', 'ilike', "%{$search}%")
                        ->orWhere('rc', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Company $company): InertiaResponse
    {
        $company->load([
            'subscription.plan',
            'users' => function ($q) {
                $q->select('users.id', 'name', 'email')
                    ->withPivot('role', 'granted_at', 'revoked_at');
            },
        ]);

        $payments = Payment::query()
            ->where('company_id', $company->id)
            ->with('plan:id,name,code')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get([
                'id', 'plan_id', 'reference', 'gateway', 'method',
                'status', 'amount_dzd', 'currency', 'paid_at', 'created_at',
            ]);

        $stats = [
            'invoices_count' => $company->invoices()->count(),
            'expenses_count' => $company->expenses()->count(),
            'documents_count' => $company->documents()->count(),
            'payments_paid_dzd' => (int) Payment::query()
                ->where('company_id', $company->id)
                ->where('status', 'paid')
                ->sum('amount_dzd'),
        ];

        return Inertia::render('Admin/Companies/Show', [
            'company' => $company,
            'payments' => $payments,
            'stats' => $stats,
        ]);
    }
}
