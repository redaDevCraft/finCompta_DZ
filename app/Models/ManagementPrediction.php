<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ManagementPrediction extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'account_id',
        'contact_id',
        'analytic_section_id',
        'period_type',
        'period_start_date',
        'period_end_date',
        'amount',
        'comment',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (ManagementPrediction $prediction) {
            if (empty($prediction->id)) {
                $prediction->id = (string) Str::uuid();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function analyticSection(): BelongsTo
    {
        return $this->belongsTo(AnalyticSection::class, 'analytic_section_id');
    }
}
