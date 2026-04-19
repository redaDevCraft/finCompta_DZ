<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if ($request->routeIs('company.select') || $request->routeIs('company.switch')) {
            return $next($request);
        }

        $userCompaniesCount = $request->user()
            ->companies()
            ->whereNull('company_users.revoked_at')
            ->count();

        // Freshly-registered user with zero companies → onboarding.
        if ($userCompaniesCount === 0) {
            return redirect()->route('onboarding.company');
        }

        $companyId = session('current_company_id') ?? $request->header('X-Company-ID');

        if (! $companyId && $userCompaniesCount === 1) {
            // Auto-pick the only company.
            $only = $request->user()
                ->companies()
                ->whereNull('company_users.revoked_at')
                ->first();
            session(['current_company_id' => $only->id]);
            $companyId = $only->id;
        }

        if (! $companyId) {
            return redirect()->route('company.select');
        }

        $company = $request->user()
            ->companies()
            ->where('companies.id', $companyId)
            ->whereNull('company_users.revoked_at')
            ->first();

        if (! $company) {
            session()->forget('current_company_id');

            return redirect()->route('company.select');
        }

        app()->instance('currentCompany', $company);

        Inertia::share('currentCompany', $company->only(
            'id',
            'raison_sociale',
            'tax_regime',
            'vat_registered'
        ));

        return $next($request);
    }
}
