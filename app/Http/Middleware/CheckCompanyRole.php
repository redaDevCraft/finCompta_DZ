<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCompanyRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! app()->has('currentCompany') || ! auth()->check()) {
            abort(403, 'Action non autorisée pour votre rôle');
        }

        $companyUser = app('currentCompany')->users()
            ->where('user_id', auth()->id())
            ->whereNull('revoked_at')
            ->first();

        if (! $companyUser || ! in_array($companyUser->pivot->role, $roles, true)) {
            abort(403, 'Action non autorisée pour votre rôle');
        }

        return $next($request);
    }
}