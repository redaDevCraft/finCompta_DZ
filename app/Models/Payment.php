<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'subscription_id',
        'plan_id',
        'reference',
        'gateway',
        'method',
        'billing_cycle',
        'amount_dzd',
        'currency',
        'status',
        'checkout_id',
        'checkout_url',
        'bon_pdf_path',
        'proof_upload_path',
        'meta',
        'paid_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'amount_dzd' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Payment $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->reference)) {
                $model->reference = 'PMT-'.strtoupper(Str::random(10));
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
