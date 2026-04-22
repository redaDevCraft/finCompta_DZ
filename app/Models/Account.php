<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'label_ar',
        'class',
        'type',
        'is_system',
        'parent_code',
        'is_active',
        'is_lettrable',
        'default_analytic_section_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'is_lettrable' => 'boolean',
        'class' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultAnalyticSection(): BelongsTo
    {
        return $this->belongsTo(AnalyticSection::class, 'default_analytic_section_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function children()
    {
        return static::query()
            ->where('company_id', $this->company_id)
            ->where('parent_code', $this->code);
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByClass(Builder $query, int $class): Builder
    {
        return $query->where('class', $class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLettrable(Builder $query): Builder
    {
        return $query->where('is_lettrable', true);
    }

    public function letterings(): HasMany
    {
        return $this->hasMany(Lettering::class);
    }

    public function isDeletable(): bool
    {
        return ! $this->is_system;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->has('currentCompany')) {
                $builder->where($builder->getModel()->getTable().'.company_id', app('currentCompany')->id);
            }
        });
    }
}
