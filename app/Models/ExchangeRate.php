<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExchangeRate extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'currency_id',
        'rate_date',
        'rate',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExchangeRate $rate) {
            if (empty($rate->id)) {
                $rate->id = (string) Str::uuid();
            }
        });
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
