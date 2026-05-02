<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InvoiceSequence extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'document_type',
        'fiscal_year',
        'prefix',
        'last_number',
        'total_issued',
        'total_voided',
        'locked',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'last_number' => 'integer',
        'total_issued' => 'integer',
        'total_voided' => 'integer',
        'locked' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (InvoiceSequence $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'sequence_id');
    }
}