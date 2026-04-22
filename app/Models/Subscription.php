<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Subscription extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'billing_cycle',
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
}
