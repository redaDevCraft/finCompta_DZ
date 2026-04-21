<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'gateway',
        'event_name',
        'signature_header',
        'payment_id',
        'signature_valid',
        'payload',
        'received_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'payload' => 'array',
        'received_at' => 'datetime',
    ];
}

