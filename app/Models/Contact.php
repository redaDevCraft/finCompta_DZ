<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contact extends Model
{
    use SoftDeletes;

    protected $primaryKey  = 'id';
    protected $keyType     = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'company_id',
        'type',
        'entity_type',
        'display_name',
        'raison_sociale',
        'nif',
        'nis',
        'rc',
        'address_line1',
        'address_wilaya',
        'email',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Contact $model) {
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->whereIn('type', ['client', 'both']);
    }

    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }
    protected static function booted(): void
{
    static::addGlobalScope('company', function (Builder $builder) {
        if (app()->has('currentCompany')) {
            $builder->where($builder->getModel()->getTable() . '.company_id', app('currentCompany')->id);
        }
    });
}
}