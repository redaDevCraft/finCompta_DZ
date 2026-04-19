<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExpenseLine extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'expense_id',
        'designation',
        'amount_ht',
        'vat_rate_pct',
        'amount_vat',
        'amount_ttc',
        'tax_rate_id',
        'account_id',
        'sort_order',
    ];

    protected $casts = [
        'amount_ht' => 'decimal:2',
        'vat_rate_pct' => 'decimal:2',
        'amount_vat' => 'decimal:2',
        'amount_ttc' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExpenseLine $line): void {
            if (empty($line->id)) {
                $line->id = (string) Str::uuid();
            }
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
