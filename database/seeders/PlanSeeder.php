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
                'tagline' => 'Pour les auto-entrepreneurs et tres petites entreprises',
                'segment' => 'solo',
                'monthly_price_dzd' => 990,
                'yearly_price_dzd' => 9900,
                'trial_days' => 14,
                'max_companies' => 1,
                'max_users' => 2,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    'invoices',
                    'quotes',
                    'expenses',
                    'basic_reports',
                    'invoice_payments',
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'tagline' => 'Pour les PME et SARL qui veulent une comptabilite complete',
                'segment' => 'sme',
                'monthly_price_dzd' => 2490,
                'yearly_price_dzd' => 24900,
                'trial_days' => 14,
                'max_companies' => 3,
                'max_users' => 10,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    'invoices',
                    'quotes',
                    'expenses',
                    'basic_reports',
                    'invoice_payments',
                    'bank_accounts',
                    'advanced_reports',
                    'analytic_accounting',
                    'multi_currency',
                    'management_predictions',
                    'auto_counterpart_rules',
                ],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'tagline' => 'Pour les cabinets comptables et grandes entreprises',
                'segment' => 'firm',
                'monthly_price_dzd' => 5990,
                'yearly_price_dzd' => 59900,
                'trial_days' => 14,
                'max_companies' => null,
                'max_users' => null,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'invoices',
                    'quotes',
                    'expenses',
                    'basic_reports',
                    'invoice_payments',
                    'bank_accounts',
                    'advanced_reports',
                    'analytic_accounting',
                    'multi_currency',
                    'management_predictions',
                    'auto_counterpart_rules',
                    'ocr',
                    'journal_permissions',
                    'firm_workspace',
                    'priority_support',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }
}
