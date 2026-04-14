<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'period_start',
        'period_end',
        'import_type',
        'source_document_path',
        'opening_balance',
        'closing_balance',
        'row_count',
        'imported_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'row_count' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'import_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}