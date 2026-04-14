<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestion extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'source_type',
        'source_id',
        'field_name',
        'suggested_value',
        'confidence',
        'accepted',
        'final_value',
    ];

    protected $casts = [
        'confidence' => 'decimal:3',
        'accepted' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted');
    }

    public function scopeForSource(Builder $query, string $type, ?string $id): Builder
    {
        return $query->where('source_type', $type)
            ->where('source_id', $id);
    }
}