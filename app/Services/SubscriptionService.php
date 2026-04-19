<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Start (or renew) a trialing subscription for a freshly-created company.
     * If a subscription already exists, returns it untouched.
     */
    public function startTrialForCompany(Company $company, ?Plan $plan = null, ?int $trialDays = null): Subscription
    {
        return DB::transaction(function () use ($company, $plan, $trialDays) {
            $existing = Subscription::query()
                ->where('company_id', $company->id)
                ->latest()
                ->first();

            if ($existing) {
                return $existing;
            }

            $plan ??= Plan::query()->where('is_active', true)->orderBy('sort_order')->first();

            $trialDays ??= (int) config('services.saas.trial_days', 3);
            $trialEnd = Carbon::now()->addDays(max(1, $trialDays));

            return Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan?->id,
                'status' => 'trialing',
                'billing_cycle' => 'monthly',
                'trial_ends_at' => $trialEnd,
                'current_period_started_at' => Carbon::now(),
                'current_period_ends_at' => $trialEnd,
            ]);
        });
    }

    /**
     * Record a successful payment and extend the subscription.
     */
    public function markPaymentSucceeded(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            if ($payment->status === 'paid') {
                return;
            }

            $payment->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
            ]);

            $subscription = $payment->subscription
                ?? Subscription::query()->where('company_id', $payment->company_id)->latest()->first();

            if (! $subscription) {
                return;
            }

            $cycle = $payment->billing_cycle ?: $subscription->billing_cycle;
            $extendFrom = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
                ? $subscription->current_period_ends_at
                : Carbon::now();

            $newEnd = $cycle === 'yearly'
                ? $extendFrom->copy()->addYear()
                : $extendFrom->copy()->addMonth();

            $subscription->update([
                'plan_id' => $payment->plan_id ?: $subscription->plan_id,
                'billing_cycle' => $cycle,
                'status' => 'active',
                'current_period_started_at' => $subscription->status === 'active'
                    ? $subscription->current_period_started_at
                    : Carbon::now(),
                'current_period_ends_at' => $newEnd,
                'last_payment_method' => $payment->method,
                'canceled_at' => null,
                'cancel_at' => null,
            ]);
        });
    }

    public function markPaymentFailed(Payment $payment, ?string $reason = null): void
    {
        $payment->update([
            'status' => 'failed',
            'meta' => array_merge($payment->meta ?? [], [
                'failure_reason' => $reason,
                'failed_at' => Carbon::now()->toIso8601String(),
            ]),
        ]);
    }

    public function cancel(Subscription $subscription, bool $immediate = false): void
    {
        if ($immediate) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => Carbon::now(),
                'cancel_at' => Carbon::now(),
            ]);

            return;
        }

        $subscription->update([
            'canceled_at' => Carbon::now(),
            'cancel_at' => $subscription->current_period_ends_at ?: Carbon::now(),
        ]);
    }
}
