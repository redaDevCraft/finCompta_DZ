<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InvoiceVatBucket extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'invoice_id',
        'tax_rate_id',
        'rate_pct',
        'base_ht',
        'vat_amount',
    ];

    protected $casts = [
        'rate_pct' => 'decimal:2',
        'base_ht' => 'decimal:2',
        'vat_amount' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (InvoiceVatBucket $model) {
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
}