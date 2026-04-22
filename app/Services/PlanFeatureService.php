<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Support\Collection;

final class PlanFeatureService
{
    /**
     * @return array<int, string>
     */
    public function allowedFeaturesForCompany(?Company $company): array
    {
        if (! $company) {
            return [];
        }

        $plan = $company->subscription()?->with('plan')->first()?->plan;
        if (! $plan) {
            return [];
        }

        if ($this->isEnterprisePlan($plan)) {
            // Enterprise must always remain fully unlocked.
            return ['*'];
        }

        if (is_array($plan->features) && $plan->features !== []) {
            return array_values(array_unique($plan->features));
        }

        $dbOverrides = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->where('enabled', true)
            ->orderBy('feature_key')
            ->pluck('feature_key')
            ->values()
            ->all();

        if ($dbOverrides !== []) {
            return array_values(array_unique($dbOverrides));
        }

        return $this->configDefaultsForPlan($plan);
    }

    public function hasFeature(?Company $company, string $feature): bool
    {
        $allowed = $this->allowedFeaturesForCompany($company);
        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($feature, $allowed, true);
    }

    /**
     * @return Collection<int, array{key:string,label:string}>
     */
    public function catalogue(): Collection
    {
        return collect([
            ['key' => 'invoices', 'label' => 'Factures'],
            ['key' => 'quotes', 'label' => 'Devis'],
            ['key' => 'expenses', 'label' => 'Depenses'],
            ['key' => 'invoice_payments', 'label' => 'Paiements facture'],
            ['key' => 'basic_reports', 'label' => 'Rapports de base'],
            ['key' => 'advanced_reports', 'label' => 'Rapports avancés'],
            ['key' => 'bank_accounts', 'label' => 'Comptes bancaires'],
            ['key' => 'ocr', 'label' => 'OCR documents'],
            ['key' => 'analytic_accounting', 'label' => 'Comptabilite analytique'],
            ['key' => 'multi_currency', 'label' => 'Multi-devise'],
            ['key' => 'management_predictions', 'label' => 'Predictions de gestion'],
            ['key' => 'auto_counterpart_rules', 'label' => 'Regles de contrepartie auto'],
            ['key' => 'journal_permissions', 'label' => 'Permissions de journaux'],
            ['key' => 'firm_workspace', 'label' => 'Espace cabinet'],
            ['key' => 'priority_support', 'label' => 'Support prioritaire'],
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function configDefaultsForPlan(Plan $plan): array
    {
        $defaults = (array) config('plan_features.defaults', []);
        $planCode = mb_strtolower((string) $plan->code);

        return array_values((array) ($defaults[$planCode] ?? []));
    }

    private function isEnterprisePlan(Plan $plan): bool
    {
        $code = mb_strtolower((string) $plan->code);
        $name = mb_strtolower((string) $plan->name);

        return in_array($code, ['enterprise', 'entreprise'], true)
            || str_contains($code, 'entrep')
            || str_contains($name, 'entrep');
    }
}

