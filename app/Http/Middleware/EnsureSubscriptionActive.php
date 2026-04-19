<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! app()->has('currentCompany')) {
            return redirect()->route('company.select');
        }

        /** @var Company $company */
        $company = app('currentCompany');
        $subscription = $company->subscription()->first();

        if (! $subscription) {
            return redirect()->route('billing.index')
                ->with('warning', 'Aucun abonnement actif — choisissez un plan pour continuer.');
        }

        $graceDays = (int) config('services.saas.grace_days', 3);

        if ($subscription->isActive() || $subscription->isInGrace($graceDays)) {
            return $next($request);
        }

        return redirect()->route('billing.index')
            ->with('warning', 'Votre essai ou abonnement a expiré. Choisissez un plan pour continuer.');
    }
}
