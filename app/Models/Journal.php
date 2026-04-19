<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Journal extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'label_ar',
        'type',
        'counterpart_account_id',
        'is_system',
        'is_active',
        'position',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Journal $journal) {
            if (empty($journal->id)) {
                $journal->id = (string) Str::uuid();
            }
        });

        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->has('currentCompany')) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    app('currentCompany')->id
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function counterpartAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counterpart_account_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'journal_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('code');
    }
}
