<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'period_id',
        'entry_date',
        'journal_code',
        'reference',
        'description',
        'status',
        'source_type',
        'source_id',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'status' => 'string',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    public function bankTransaction()
{
    return $this->hasOne(BankTransaction::class, 'journal_entry_id');
}

    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort_order');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function isBalanced(): bool
    {
        $this->loadMissing('lines');

        return (float) $this->lines->sum('debit') === (float) $this->lines->sum('credit');
    }

    public function isPostable(): bool
    {
        return $this->status === 'draft' && $this->isBalanced();
    }
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (JournalEntry $entry) {
            $period = FiscalPeriod::find($entry->period_id);

            if ($period && $period->isLocked()) {
                throw new \RuntimeException(
                    'Période comptable clôturée — aucune écriture possible'
                );
            }
        });
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
