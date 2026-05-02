<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuoteLine extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'quote_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'vat_rate',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'vat_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuoteLine $line) {
            if (empty($line->id)) {
                $line->id = (string) Str::uuid();
            }
        });
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
