<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JournalEntry extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'period_id',
        'journal_id',
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

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
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

        $debit = round((float) $this->lines->sum('debit'), 2);
        $credit = round((float) $this->lines->sum('credit'), 2);

        return abs($debit - $credit) < 0.01;
    }

    public function isPostable(): bool
    {
        return $this->status === 'draft' && $this->isBalanced();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (JournalEntry $entry) {
            if (empty($entry->id)) {
                $entry->id = (string) Str::uuid();
            }

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
                $builder->where($builder->getModel()->getTable().'.company_id', app('currentCompany')->id);
            }
        });
    }
}
