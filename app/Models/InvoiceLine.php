<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InvoiceLine extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoice_id',
        'sort_order',
        'designation',
        'quantity',
        'unit',
        'unit_price_ht',
        'discount_pct',
        'line_ht',
        'tax_rate_id',
        'vat_rate_pct',
        'line_vat',
        'line_ttc',
        'account_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price_ht' => 'decimal:4',
        'discount_pct' => 'decimal:2',
        'line_ht' => 'decimal:2',
        'vat_rate_pct' => 'decimal:2',
        'line_vat' => 'decimal:2',
        'line_ttc' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (InvoiceLine $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}