<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Services\PlanFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanFeatureController extends Controller
{
    public function __construct(private readonly PlanFeatureService $service) {}

    public function index(): Response
    {
        $catalogue = $this->service->catalogue()->values()->all();

        $plans = Plan::query()
            ->with('planFeatures:id,plan_id,feature_key,enabled,limits')
            ->orderBy('sort_order')
            ->orderBy('monthly_price_dzd')
            ->get()
            ->map(function (Plan $plan) {
                $enabled = $plan->planFeatures
                    ->where('enabled', true)
                    ->pluck('feature_key')
                    ->values()
                    ->all();

                $limits = $plan->planFeatures
                    ->mapWithKeys(static fn (PlanFeature $feature) => [
                        $feature->feature_key => is_array($feature->limits) ? $feature->limits : [],
                    ])
                    ->all();

                return [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'features' => $enabled,
                    'limits' => $limits,
                ];
            })
            ->values();

        return Inertia::render('Admin/Plans/Features', [
            'catalogue' => $catalogue,
            'plans' => $plans,
            'limitDefinitions' => [
                'invoicing' => ['key' => 'max', 'label' => 'Max factures / mois'],
                'contacts' => ['key' => 'max', 'label' => 'Max utilisateurs / société'],
                'ocr' => ['key' => 'max', 'label' => 'Max documents OCR / mois'],
            ],
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable', 'array'],
            'limits.*.max' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $selected = collect($data['features'] ?? [])
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->unique()
            ->values();

        PlanFeature::query()->where('plan_id', $plan->id)->delete();

        $limits = collect($data['limits'] ?? []);

        foreach ($selected as $key) {
            $featureLimits = $limits->get($key);
            if (! is_array($featureLimits)) {
                $featureLimits = [];
            }

            PlanFeature::query()->create([
                'plan_id' => $plan->id,
                'feature_key' => $key,
                'enabled' => true,
                'limits' => $featureLimits,
            ]);
        }

        return back()->with('success', 'Fonctionnalités du plan mises à jour.');
    }
}

