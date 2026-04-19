<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar_url' => $request->user()->avatar_url,
                ] : null,
                'roles' => fn () => $request->user()
                    ? $request->user()->getRoleNames()->values()->all()
                    : [],
                'permissions' => fn () => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'subscription' => function () use ($request) {
                if (! $request->user() || ! app()->has('currentCompany')) {
                    return null;
                }
                $sub = app('currentCompany')->subscription()->with('plan:id,code,name')->first();
                if (! $sub) {
                    return null;
                }

                return [
                    'status' => $sub->status,
                    'is_on_trial' => $sub->isOnTrial(),
                    'is_active' => $sub->isActive(),
                    'days_remaining' => $sub->daysRemaining(),
                    'trial_ends_at' => optional($sub->trial_ends_at)?->toIso8601String(),
                    'plan' => $sub->plan ? [
                        'code' => $sub->plan->code,
                        'name' => $sub->plan->name,
                    ] : null,
                ];
            },
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
