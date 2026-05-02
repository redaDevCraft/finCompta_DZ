<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'plan_id',
        'next_plan_id',
        'status',
        'billing_cycle',
        'next_billing_cycle',
        'next_change_effective_at',
        'pending_change_reason',
        'pending_change_requested_at',
        'trial_ends_at',
        'current_period_started_at',
        'current_period_ends_at',
        'grace_ends_at',
        'canceled_at',
        'cancel_at',
        'last_payment_method',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_started_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'next_change_effective_at' => 'datetime',
        'pending_change_requested_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'cancel_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Subscription $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function nextPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'next_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isActive(): bool
    {
        if ($this->isOnTrial()) {
            return true;
        }

        if ($this->status !== 'active') {
            return false;
        }

        return $this->current_period_ends_at === null
            || $this->current_period_ends_at->isFuture();
    }

    public function isInGrace(int $graceDays = 3): bool
    {
        if (! in_array($this->status, ['past_due', 'active'], true)) {
            return false;
        }

        if (! $this->current_period_ends_at) {
            return false;
        }

        if ($this->current_period_ends_at->isFuture()) {
            return false;
        }

        if ($this->grace_ends_at) {
            return $this->grace_ends_at->isFuture();
        }

        return $this->current_period_ends_at
            ->copy()
            ->addDays($graceDays)
            ->isFuture();
    }

    public function daysRemaining(): int
    {
        $target = $this->isOnTrial()
            ? $this->trial_ends_at
            : $this->current_period_ends_at;

        if (! $target) {
            return 0;
        }

        return max(0, Carbon::now()->diffInDays($target, false));
    }

    public function hasScheduledChange(): bool
    {
        return ! empty($this->next_plan_id) && ! empty($this->next_change_effective_at);
    }

    public function scheduledPlan(): ?Plan
    {
        if (! $this->next_plan_id) {
            return null;
        }

        return $this->nextPlan;
    }

    public function scheduledChangeIsDowngrade(): bool
    {
        $currentPlan = $this->plan;
        $nextPlan = $this->scheduledPlan();
        if (! $currentPlan || ! $nextPlan) {
            return false;
        }

        $nextCycle = $this->next_billing_cycle ?: $this->billing_cycle;

        return $nextPlan->priceForCycle($nextCycle) < $currentPlan->priceForCycle($this->billing_cycle);
    }

    public function scheduledChangeIsUpgrade(): bool
    {
        $currentPlan = $this->plan;
        $nextPlan = $this->scheduledPlan();
        if (! $currentPlan || ! $nextPlan) {
            return false;
        }

        $nextCycle = $this->next_billing_cycle ?: $this->billing_cycle;

        return $nextPlan->priceForCycle($nextCycle) > $currentPlan->priceForCycle($this->billing_cycle);
    }

    public function effectivePlan(): ?Plan
    {
        if (
            $this->next_plan_id &&
            $this->next_change_effective_at &&
            $this->next_change_effective_at->isPast()
        ) {
            return $this->scheduledPlan();
        }

        return $this->plan;
    }

    public function effectiveBillingCycle(): string
    {
        if (
            $this->next_billing_cycle &&
            $this->next_change_effective_at &&
            $this->next_change_effective_at->isPast()
        ) {
            return $this->next_billing_cycle;
        }

        return $this->billing_cycle ?: 'monthly';
    }
}
