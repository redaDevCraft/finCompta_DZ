<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AnalyticSection extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'analytic_axis_id',
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnalyticSection $section) {
            if (empty($section->id)) {
                $section->id = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function axis(): BelongsTo
    {
        return $this->belongsTo(AnalyticAxis::class, 'analytic_axis_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'analytic_section_id');
    }
}
