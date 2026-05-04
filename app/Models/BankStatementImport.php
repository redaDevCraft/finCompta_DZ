<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    use HasUuids;
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
        'file_name',
        'file_path',
        'mime_type',
        'document_id',  // for pdf_ocr flow linking to documents table
        'status',
        'meta',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'row_count' => 'integer',
        'meta'=> 'array',
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