<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TaxRate extends Model
{
    protected $primaryKey  = 'id';
    protected $keyType     = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'company_id',
        'label',
        'rate_percent',
        'tax_type',
        'is_recoverable',
        'reporting_code',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'rate_percent'   => 'decimal:2',
        'is_recoverable' => 'boolean',
        'is_active'      => 'boolean',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (TaxRate $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /**
     * Active = is_active true AND (effective_to is null OR effective_to >= today)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now()->toDateString());
            });
    }

    /**
     * Returns rates for a specific company OR global system-wide rates (company_id = null)
     */
    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where(function (Builder $q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->orWhereNull('company_id');
        });
    }
}