<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'import_id',
        'transaction_date',
        'value_date',
        'label',
        'amount',
        'direction',
        'balance_after',
        'reconcile_status',
        'journal_entry_id',
        'matched_by',
        'matched_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'import_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('reconcile_status', 'unmatched');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
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