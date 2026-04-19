<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LandingController extends Controller
{
    public function home(Request $request): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'id', 'code', 'name', 'tagline',
                'monthly_price_dzd', 'yearly_price_dzd',
                'trial_days', 'features',
            ]);

        return Inertia::render('Landing/Home', [
            'plans' => $plans,
            'trialDays' => (int) config('services.saas.trial_days', 3),
            'brand' => [
                'name' => 'FinCompta DZ',
                'tagline' => 'Votre comptabilité algérienne — dans le cloud.',
            ],
        ]);
    }

    public function pricing(Request $request): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Landing/Pricing', [
            'plans' => $plans,
            'trialDays' => (int) config('services.saas.trial_days', 3),
        ]);
    }

    /**
     * Public "start trial" entry:
     *   - unauthenticated -> Google OAuth with preserved intent
     *   - authenticated   -> onboarding (if no company) or billing checkout
     */
    public function startTrial(Request $request): RedirectResponse|SymfonyResponse
    {
        $plan = $request->query('plan', 'starter');
        $cycle = in_array($request->query('cycle'), ['monthly', 'yearly'], true)
            ? $request->query('cycle')
            : 'monthly';

        if (! $request->user()) {
            $url = route('auth.google.redirect', [
                'intent' => 'subscribe',
                'plan' => $plan,
                'cycle' => $cycle,
            ], absolute: true);

            return $this->inertiaNavigate($request, $url);
        }

        $hasCompany = $request->user()->companies()->whereNull('company_users.revoked_at')->exists();

        if (! $hasCompany) {
            session(['oauth_intent' => [
                'intent' => 'subscribe',
                'plan' => $plan,
                'cycle' => $cycle,
            ]]);

            return $this->inertiaNavigate($request, route('onboarding.company', absolute: true));
        }

        return $this->inertiaNavigate($request, route('billing.checkout', [
            'plan' => $plan,
            'cycle' => $cycle,
        ], absolute: true));
    }

    /**
     * Inertia SPA visits use XHR; OAuth must run in a full browser navigation.
     */
    private function inertiaNavigate(Request $request, string $url): RedirectResponse|SymfonyResponse
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }
}
