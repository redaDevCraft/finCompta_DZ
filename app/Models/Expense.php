<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'contact_id',
        'supplier_snapshot',
        'reference',
        'expense_date',
        'due_date',
        'description',
        'total_ht',
        'total_vat',
        'total_ttc',
        'account_id',
        'status',
        'source_document_id',
        'ai_extracted',
        'journal_entry_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'due_date' => 'date',
        'total_ht' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'ai_extracted' => 'boolean',
        'supplier_snapshot' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class)->orderBy('sort_order');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if (empty($expense->id)) {
                $expense->id = (string) Str::uuid();
            }
        });

        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->has('currentCompany')) {
                $builder->where($builder->getModel()->getTable().'.company_id', app('currentCompany')->id);
            }
        });
    }
}
