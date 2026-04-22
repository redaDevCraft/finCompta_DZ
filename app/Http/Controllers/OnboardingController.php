<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function showCompany(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->companies()->whereNull('company_users.revoked_at')->exists()) {
            return redirect()->route('dashboard');
        }

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'id', 'code', 'name', 'tagline',
                'monthly_price_dzd', 'yearly_price_dzd',
                'trial_days', 'features', 'segment',
                'max_companies', 'max_users', 'is_default',
            ]);

        return Inertia::render('Onboarding/Company', [
            'plans' => $plans,
            'trialDays' => (int) config('services.saas.trial_days', 3),
            'presetPlan' => session('oauth_intent.plan'),
            'presetCycle' => session('oauth_intent.cycle', 'monthly'),
        ]);
    }

    public function storeCompany(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'forme_juridique' => ['required', 'in:SARL,EURL,SPA,SNC,EI,SNCA'],
            'nif' => ['required', 'string', 'max:30'],
            'nis' => ['required', 'string', 'max:30'],
            'rc' => ['required', 'string', 'max:50'],
            'ai' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['required', 'string', 'max:500'],
            'address_wilaya' => ['required', 'string', 'max:100'],
            'tax_regime' => ['required', 'in:IBS,IRG,IFU'],
            'vat_registered' => ['required', 'boolean'],
            'fiscal_year_end' => ['required', 'integer', 'min:1', 'max:12'],
            'currency' => ['required', 'string', 'size:3'],
            'plan_code' => ['nullable', 'string', 'exists:plans,code'],
        ]);

        $plan = null;
        if (! empty($validated['plan_code'])) {
            $plan = Plan::query()->where('code', $validated['plan_code'])->where('is_active', true)->first();
        }

        $activeCompaniesCount = $user->companies()->whereNull('company_users.revoked_at')->count();
        if ($plan && $plan->max_companies !== null && $activeCompaniesCount >= (int) $plan->max_companies) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'Votre plan permet au maximum '.$plan->max_companies.' societes. Merci de passer au plan superieur.');
        }

        $company = DB::transaction(function () use ($validated, $user, $subscriptions, $plan) {
            $company = Company::create([
                'raison_sociale' => $validated['raison_sociale'],
                'forme_juridique' => $validated['forme_juridique'],
                'nif' => $validated['nif'],
                'nis' => $validated['nis'],
                'rc' => $validated['rc'],
                'ai' => $validated['ai'] ?? null,
                'address_line1' => $validated['address_line1'],
                'address_wilaya' => $validated['address_wilaya'],
                'tax_regime' => $validated['tax_regime'],
                'vat_registered' => (bool) $validated['vat_registered'],
                'fiscal_year_end' => (int) $validated['fiscal_year_end'],
                'currency' => $validated['currency'],
                'status' => 'active',
            ]);

            DB::table('company_users')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'granted_at' => now(),
                'revoked_at' => null,
            ]);

            $subscriptions->startTrialForCompany($company, $plan);

            return $company;
        });

        session(['current_company_id' => $company->id]);

        // If user came here via "Start trial → pick plan" intent, send to billing next.
        $intent = session()->pull('oauth_intent');
        if (is_array($intent) && ($intent['intent'] ?? null) === 'subscribe' && ! empty($intent['plan'])) {
            return redirect()->route('billing.checkout', [
                'plan' => $intent['plan'],
                'cycle' => $intent['cycle'] ?? 'monthly',
            ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Votre entreprise est prête — profitez de votre essai gratuit.');
    }
}
