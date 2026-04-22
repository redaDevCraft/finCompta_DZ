<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AutoCounterpartRule extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'trigger_account_id',
        'trigger_direction',
        'counterpart_account_id',
        'counterpart_direction',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (AutoCounterpartRule $rule) {
            if (empty($rule->id)) {
                $rule->id = (string) Str::uuid();
            }
        });
    }

    public function triggerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'trigger_account_id');
    }

    public function counterpartAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counterpart_account_id');
    }
}
