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
    private function planPricePerCycle(Plan $plan, string $cycle): int
    {
        return $plan->priceForCycle($cycle);
    }

    private function classifyChange(?Subscription $subscription, Plan $newPlan, string $newCycle): string
    {
        if (! $subscription || ! $subscription->plan) {
            return 'new';
        }

        $currentPrice = $this->planPricePerCycle($subscription->plan, $subscription->billing_cycle);
        $newPrice = $this->planPricePerCycle($newPlan, $newCycle);

        if ($newPrice > $currentPrice) {
            return 'upgrade';
        }

        if ($newPrice < $currentPrice) {
            return 'downgrade';
        }

        if ($subscription->billing_cycle !== $newCycle) {
            return 'cycle_change';
        }

        return 'lateral';
    }

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
                'grace_ends_at' => null,
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

            if ($subscription && ! $payment->subscription_id) {
                $payment->subscription()->associate($subscription);
                $payment->save();
            }

            $plan = $payment->plan ?? $subscription?->plan;
            if (! $plan) {
                return;
            }

            $cycle = $payment->billing_cycle ?: $subscription?->billing_cycle ?: 'monthly';
            $changeType = $this->classifyChange($subscription, $plan, $cycle);

            $extendFrom = $subscription && $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
                ? $subscription->current_period_ends_at
                : Carbon::now();

            $newEnd = $cycle === 'yearly'
                ? $extendFrom->copy()->addYear()
                : $extendFrom->copy()->addMonth();

            if (! $subscription) {
                Subscription::create([
                    'company_id' => $payment->company_id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'billing_cycle' => $cycle,
                    'current_period_started_at' => Carbon::now(),
                    'current_period_ends_at' => $newEnd,
                    'last_payment_method' => $payment->method,
                    'grace_ends_at' => null,
                    'canceled_at' => null,
                    'cancel_at' => null,
                ]);

                $this->revokeCompanyUserTokens($payment->company_id);

                return;
            }

            if ($changeType === 'new' || $subscription->status === 'trialing') {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $cycle,
                    'status' => 'active',
                    'current_period_started_at' => Carbon::now(),
                    'current_period_ends_at' => $newEnd,
                    'last_payment_method' => $payment->method,
                    'grace_ends_at' => null,
                    'canceled_at' => null,
                    'cancel_at' => null,
                ]);

                $this->revokeCompanyUserTokens($payment->company_id);

                return;
            }

            if ($changeType === 'downgrade') {
                $subscription->update([
                    'status' => 'active',
                    'billing_cycle' => $subscription->billing_cycle,
                    'current_period_ends_at' => $extendFrom,
                    'last_payment_method' => $payment->method,
                    'next_plan_id' => $plan->id,
                    'next_billing_cycle' => $cycle,
                    'next_change_effective_at' => $newEnd,
                    'pending_change_reason' => 'downgrade',
                    'pending_change_requested_at' => Carbon::now(),
                    'grace_ends_at' => null,
                    'canceled_at' => null,
                    'cancel_at' => null,
                ]);

                $this->revokeCompanyUserTokens($payment->company_id);

                return;
            }

            if ($changeType === 'cycle_change' && $subscription->billing_cycle === 'yearly' && $cycle === 'monthly') {
                $subscription->update([
                    'status' => 'active',
                    'next_plan_id' => $subscription->plan_id,
                    'next_billing_cycle' => 'monthly',
                    'next_change_effective_at' => $subscription->current_period_ends_at ?: $newEnd,
                    'pending_change_reason' => 'cycle_change',
                    'pending_change_requested_at' => Carbon::now(),
                    'last_payment_method' => $payment->method,
                    'grace_ends_at' => null,
                    'canceled_at' => null,
                    'cancel_at' => null,
                ]);

                $this->revokeCompanyUserTokens($payment->company_id);

                return;
            }

            $subscription->update([
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'status' => 'active',
                'current_period_started_at' => $subscription->status === 'active'
                    ? $subscription->current_period_started_at
                    : Carbon::now(),
                'current_period_ends_at' => $newEnd,
                'last_payment_method' => $payment->method,
                'next_plan_id' => null,
                'next_billing_cycle' => null,
                'next_change_effective_at' => null,
                'pending_change_reason' => null,
                'pending_change_requested_at' => null,
                'grace_ends_at' => null,
                'canceled_at' => null,
                'cancel_at' => null,
            ]);

            $this->revokeCompanyUserTokens($payment->company_id);
        });
    }

    public function applyScheduledChanges(Subscription $subscription): void
    {
        if (! $subscription->next_plan_id || ! $subscription->next_change_effective_at) {
            return;
        }

        if ($subscription->next_change_effective_at->isFuture()) {
            return;
        }

        $subscription->update([
            'plan_id' => $subscription->next_plan_id,
            'billing_cycle' => $subscription->next_billing_cycle ?: $subscription->billing_cycle,
            'next_plan_id' => null,
            'next_billing_cycle' => null,
            'next_change_effective_at' => null,
            'pending_change_reason' => null,
            'pending_change_requested_at' => null,
        ]);
    }

    public function markPaymentFailed(Payment $payment, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment->update([
                'status' => 'failed',
                'meta' => array_merge($payment->meta ?? [], [
                    'failure_reason' => $reason,
                    'failed_at' => Carbon::now()->toIso8601String(),
                ]),
            ]);

            $subscription = $payment->subscription
                ?? Subscription::query()->where('company_id', $payment->company_id)->latest()->first();

            if ($subscription && $subscription->current_period_ends_at && $subscription->current_period_ends_at->isPast()) {
                $graceDays = max(0, (int) config('services.saas.grace_days', 3));
                $subscription->update([
                    'status' => 'past_due',
                    'grace_ends_at' => $subscription->current_period_ends_at->copy()->addDays($graceDays),
                ]);
            }
        });
    }

    public function cancel(Subscription $subscription, bool $immediate = false): void
    {
        if ($immediate) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => Carbon::now(),
                'cancel_at' => Carbon::now(),
                'grace_ends_at' => null,
            ]);

            $this->revokeCompanyUserTokens($subscription->company_id);

            return;
        }

        $subscription->update([
            'canceled_at' => Carbon::now(),
            'cancel_at' => $subscription->current_period_ends_at ?: Carbon::now(),
        ]);
    }

    private function revokeCompanyUserTokens(string $companyId): void
    {
        $company = Company::query()->with('users')->find($companyId);
        if (! $company) {
            return;
        }

        foreach ($company->users as $user) {
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        }
    }
}
