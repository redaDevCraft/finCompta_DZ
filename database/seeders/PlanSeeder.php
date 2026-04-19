<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'tagline' => 'Pour les auto-entrepreneurs et TPE',
                'monthly_price_dzd' => 1990,
                'yearly_price_dzd' => 19900,
                'trial_days' => 3,
                'max_users' => 2,
                'max_invoices_per_month' => 30,
                'max_documents_per_month' => 50,
                'features' => [
                    'Facturation conforme DGI',
                    'Dépenses & justificatifs',
                    'OCR (30/mois)',
                    'Rapports TVA (G50)',
                    'Grand livre & balance',
                    'Support par email',
                ],
                'sort_order' => 10,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'tagline' => 'Pour PME actives',
                'monthly_price_dzd' => 4990,
                'yearly_price_dzd' => 49900,
                'trial_days' => 3,
                'max_users' => 6,
                'max_invoices_per_month' => 300,
                'max_documents_per_month' => 500,
                'features' => [
                    'Tout Starter',
                    'Lettrage auto des comptes',
                    'Rapprochement bancaire',
                    'Balance âgée clients/fournisseurs',
                    'Bilan & CR (SCF)',
                    'Multi-utilisateurs',
                    'Support prioritaire',
                ],
                'sort_order' => 20,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Entreprise',
                'tagline' => 'Sur mesure, sans limite',
                'monthly_price_dzd' => 12990,
                'yearly_price_dzd' => 129900,
                'trial_days' => 3,
                'max_users' => null,
                'max_invoices_per_month' => null,
                'max_documents_per_month' => null,
                'features' => [
                    'Tout Pro',
                    'Utilisateurs illimités',
                    'API & intégrations',
                    'Multi-sociétés',
                    'Audit trail avancé',
                    'Support dédié (WhatsApp)',
                    'Onboarding personnalisé',
                ],
                'sort_order' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan + ['is_active' => true]
            );
        }
    }
}
