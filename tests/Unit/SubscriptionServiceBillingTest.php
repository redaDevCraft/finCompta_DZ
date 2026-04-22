<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionServiceBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_payment_succeeded_activates_trial_subscription(): void
    {
        $company = $this->makeCompany();
        $starter = $this->makePlan('starter', 1000, 10000);
        $trial = Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $starter->id,
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => now()->addDays(3),
            'current_period_started_at' => now(),
            'current_period_ends_at' => now()->addDays(3),
        ]);

        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $trial->id,
            'plan_id' => $starter->id,
            'gateway' => 'chargily',
            'method' => 'cib',
            'billing_cycle' => 'monthly',
            'amount_dzd' => 1000,
            'currency' => 'DZD',
            'status' => 'pending',
            'approval_status' => 'none',
        ]);

        app(SubscriptionService::class)->markPaymentSucceeded($payment);

        $trial->refresh();
        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('active', $trial->status);
        $this->assertSame('monthly', $trial->billing_cycle);
        $this->assertNull($trial->next_plan_id);
        $this->assertNotNull($trial->current_period_ends_at);
    }

    public function test_mark_payment_succeeded_schedules_downgrade_without_changing_current_plan(): void
    {
        $company = $this->makeCompany();
        $enterprise = $this->makePlan('enterprise', 8000, 80000);
        $starter = $this->makePlan('starter', 1000, 10000);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $enterprise->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_started_at' => now()->subDays(10),
            'current_period_ends_at' => now()->addDays(20),
        ]);

        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $starter->id,
            'gateway' => 'bon_de_commande',
            'method' => 'bank_transfer',
            'billing_cycle' => 'monthly',
            'amount_dzd' => 1000,
            'currency' => 'DZD',
            'status' => 'pending',
            'approval_status' => 'proof_uploaded',
        ]);

        app(SubscriptionService::class)->markPaymentSucceeded($payment);

        $subscription->refresh();
        $this->assertSame($enterprise->id, $subscription->plan_id);
        $this->assertSame($starter->id, $subscription->next_plan_id);
        $this->assertSame('downgrade', $subscription->pending_change_reason);
        $this->assertNotNull($subscription->next_change_effective_at);
    }

    public function test_apply_scheduled_changes_switches_plan_and_clears_pending_fields(): void
    {
        $company = $this->makeCompany();
        $pro = $this->makePlan('pro', 3000, 30000);
        $starter = $this->makePlan('starter', 1000, 10000);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'billing_cycle' => 'yearly',
            'current_period_started_at' => now()->subYear(),
            'current_period_ends_at' => now()->subMinute(),
            'next_plan_id' => $starter->id,
            'next_billing_cycle' => 'monthly',
            'next_change_effective_at' => now()->subMinute(),
            'pending_change_reason' => 'downgrade',
            'pending_change_requested_at' => now()->subDays(3),
        ]);

        app(SubscriptionService::class)->applyScheduledChanges($subscription);

        $subscription->refresh();
        $this->assertSame($starter->id, $subscription->plan_id);
        $this->assertSame('monthly', $subscription->billing_cycle);
        $this->assertNull($subscription->next_plan_id);
        $this->assertNull($subscription->next_billing_cycle);
        $this->assertNull($subscription->next_change_effective_at);
    }

    private function makePlan(string $code, int $monthly, int $yearly): Plan
    {
        return Plan::query()->create([
            'code' => $code.'-'.Str::lower(Str::random(6)),
            'name' => Str::title($code),
            'monthly_price_dzd' => $monthly,
            'yearly_price_dzd' => $yearly,
            'trial_days' => 3,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function makeCompany(): Company
    {
        $id = (string) Str::uuid();
        $suffix = Str::upper(Str::random(8));

        DB::table('companies')->insert([
            'id' => $id,
            'raison_sociale' => 'Company '.$suffix,
            'forme_juridique' => 'EURL',
            'nif' => 'NIF-'.$suffix,
            'nis' => 'NIS-'.$suffix,
            'rc' => 'RC-'.$suffix,
            'ai' => null,
            'address_line1' => 'Adresse '.$suffix,
            'address_wilaya' => 'Alger',
            'tax_regime' => 'IBS',
            'vat_registered' => true,
            'fiscal_year_end' => 12,
            'currency' => 'DZD',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        return Company::query()->findOrFail($id);
    }
}
