<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PlanFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function __construct(private readonly PlanFeatureService $features) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $company = app()->has('currentCompany') ? app('currentCompany') : null;
        if (! $this->features->hasFeature($company, $feature)) {
            return redirect()
                ->route('billing.checkout')
                ->with('warning', 'Fonctionnalité non incluse dans votre plan actuel. Passez au plan supérieur.');
        }

        return $next($request);
    }
}

