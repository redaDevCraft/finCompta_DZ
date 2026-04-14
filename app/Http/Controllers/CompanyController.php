<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function select(Request $request): Response
    {
        $companies = $request->user()
            ->companies()
            ->whereNull('company_users.revoked_at')
            ->get([
                'companies.id',
                'companies.raison_sociale',
                'companies.tax_regime',
                'companies.vat_registered',
                'companies.status',
            ]);

        return Inertia::render('companies/CompanySelect', [
            'companies' => $companies,
        ]);
    }

    public function switchCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'uuid'],
        ]);

        $company = $request->user()
            ->companies()
            ->where('companies.id', $validated['company_id'])
            ->whereNull('company_users.revoked_at')
            ->firstOrFail();

        session(['current_company_id' => $company->id]);

        return redirect()->route('dashboard');
    }
}