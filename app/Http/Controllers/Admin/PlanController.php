<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PlanController extends Controller
{
    public function index(): InertiaResponse
    {
        $plans = Plan::query()
            ->withCount(['subscriptions as active_subscriptions_count' => function ($q) {
                $q->whereIn('status', ['trialing', 'active']);
            }])
            ->orderBy('sort_order')
            ->orderBy('monthly_price_dzd')
            ->get();

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        Plan::create($validated);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan « '.$validated['name'].' » créé.');
    }

    public function edit(Plan $plan): InertiaResponse
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => $plan,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $this->validatePayload($request, $plan);

        $plan->update($validated);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan « '.$plan->name.' » mis à jour.');
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with(
            'success',
            $plan->is_active
                ? 'Plan activé.'
                : 'Plan désactivé.',
        );
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors([
                'plan' => 'Impossible de supprimer ce plan : des abonnements y sont rattachés. Désactivez-le à la place.',
            ]);
        }

        $plan->delete();

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Plan supprimé.');
    }

    private function validatePayload(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('plans', 'code')->ignore($plan?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'monthly_price_dzd' => ['required', 'integer', 'min:0'],
            'yearly_price_dzd' => ['required', 'integer', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_invoices_per_month' => ['nullable', 'integer', 'min:0'],
            'max_documents_per_month' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['features'] = array_values(array_filter(
            $data['features'] ?? [],
            fn ($v) => is_string($v) && trim($v) !== '',
        ));

        return $data;
    }
}
