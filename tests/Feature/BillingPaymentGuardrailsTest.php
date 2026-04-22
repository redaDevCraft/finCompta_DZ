<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ChargilyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingPaymentGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_chargily_checkout_is_blocked_when_one_is_processing(): void
    {
        [$user, $company] = $this->makeUserAndCompanyContext();
        $plan = $this->makePlan('pro', 3000, 30000);
        $this->makeSubscription($company, $plan, 'monthly');

        $chargily = \Mockery::mock(ChargilyService::class);
        $chargily->shouldReceive('createCheckout')
            ->once()
            ->andReturn([
                'ok' => true,
                'id' => 'chk_1',
                'url' => 'https://checkout.test/1',
                'raw' => ['id' => 'chk_1'],
            ]);
        $this->instance(ChargilyService::class, $chargily);

        $payload = [
            'plan_code' => $plan->code,
            'cycle' => 'monthly',
            'method' => 'cib',
        ];

        $this->actingAs($user)->withSession(['current_company_id' => $company->id])->post('/billing/chargily', $payload);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id])->post('/billing/chargily', $payload);

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_webhook_failure_event_is_ignored_after_payment_is_paid(): void
    {
        $company = $this->makeCompany();
        $plan = $this->makePlan('starter', 1000, 10000);
        $subscription = $this->makeSubscription($company, $plan, 'monthly');
        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'gateway' => 'chargily',
            'method' => 'edahabia',
            'billing_cycle' => 'monthly',
            'amount_dzd' => 1000,
            'currency' => 'DZD',
            'status' => 'processing',
            'approval_status' => 'none',
            'checkout_id' => 'chk_2',
        ]);

        $chargily = \Mockery::mock(ChargilyService::class);
        $chargily->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $this->instance(ChargilyService::class, $chargily);

        $successPayload = [
            'type' => 'checkout.succeeded',
            'data' => [
                'id' => 'evt_success_1',
                'amount' => 1000,
                'currency' => 'DZD',
                'metadata' => ['payment_id' => $payment->id],
            ],
        ];
        $failurePayload = [
            'type' => 'payment.failed',
            'data' => [
                'id' => 'evt_fail_1',
                'amount' => 1000,
                'currency' => 'DZD',
                'metadata' => ['payment_id' => $payment->id],
                'failure_reason' => 'declined',
            ],
        ];

        $this->postJson('/webhooks/chargily', $successPayload, ['signature' => 'valid'])->assertOk();
        $this->postJson('/webhooks/chargily', $failurePayload, ['signature' => 'valid'])->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_pending_bon_blocks_new_chargily_checkout(): void
    {
        [$user, $company] = $this->makeUserAndCompanyContext();
        $plan = $this->makePlan('starter', 1000, 10000);
        $subscription = $this->makeSubscription($company, $plan, 'monthly');

        Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'gateway' => 'bon_de_commande',
            'method' => 'bank_transfer',
            'billing_cycle' => 'monthly',
            'amount_dzd' => 1000,
            'currency' => 'DZD',
            'status' => 'pending',
            'approval_status' => 'proof_missing',
        ]);

        $chargily = \Mockery::mock(ChargilyService::class);
        $chargily->shouldNotReceive('createCheckout');
        $this->instance(ChargilyService::class, $chargily);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->post('/billing/chargily', [
                'plan_code' => $plan->code,
                'cycle' => 'monthly',
                'method' => 'cib',
            ]);

        $this->assertSame(1, Payment::query()->count());
    }

    private function makeSubscription(Company $company, Plan $plan, string $cycle): Subscription
    {
        return Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $cycle,
            'current_period_started_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);
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

    private function makeUserAndCompanyContext(): array
    {
        $user = User::factory()->create();
        $company = $this->makeCompany();
        DB::table('company_users')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'granted_at' => now(),
            'revoked_at' => null,
        ]);

        return [$user, $company];
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
