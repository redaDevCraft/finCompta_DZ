<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function company(): Response
    {
        return Inertia::render('Settings/Company', [
            'company' => app('currentCompany')->only([
                'id',
                'raison_sociale',
                'forme_juridique',
                'nif',
                'nis',
                'rc',
                'ai',
                'address_line1',
                'address_line2',
                'address_wilaya',
                'address_postal_code',
                'tax_regime',
                'vat_registered',
                'currency',
            ]),
        ]);
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $company = app('currentCompany');

        $validated = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'forme_juridique' => ['required', 'in:SARL,EURL,SPA,SNC,EI,SNCA'],
            'nif' => ['nullable', 'string', 'max:30'],
            'nis' => ['nullable', 'string', 'max:30'],
            'rc' => ['nullable', 'string', 'max:50'],
            'ai' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:500'],
            'address_wilaya' => ['nullable', 'string', 'max:100'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'tax_regime' => ['required', 'string', 'max:50'],
            'vat_registered' => ['required', 'boolean'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $company->update($validated);

        return back()->with('success', 'Paramètres de l’entreprise mis à jour.');
    }

    public function accounts(): Response
    {
        $companyId = app('currentCompany')->id;

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->orderBy('class')
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'label',
                'type',
                'class',
                'is_system',
                'is_active',
            ])
            ->groupBy('class')
            ->map(fn ($items) => $items->values());

        return Inertia::render('Settings/Accounts', [
            'accountsByClass' => $accounts,
        ]);
    }
}
