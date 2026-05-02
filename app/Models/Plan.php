<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'segment',
        'name',
        'tagline',
        'monthly_price_dzd',
        'yearly_price_dzd',
        'trial_days',
        'features',
        'max_companies',
        'max_users',
        'max_invoices_per_month',
        'max_documents_per_month',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'monthly_price_dzd' => 'integer',
        'yearly_price_dzd' => 'integer',
        'trial_days' => 'integer',
        'max_companies' => 'integer',
        'max_users' => 'integer',
        'max_invoices_per_month' => 'integer',
        'max_documents_per_month' => 'integer',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Plan $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function priceForCycle(string $cycle): int
    {
        return $cycle === 'yearly' ? (int) $this->yearly_price_dzd : (int) $this->monthly_price_dzd;
    }

    public function hasFeature(string $featureCode): bool
    {
        return in_array($featureCode, $this->features ?? [], true);
    }
}
