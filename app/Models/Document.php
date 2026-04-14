<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Document extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'storage_key',
        'document_type',
        'source',
        'ocr_status',
        'ocr_raw_text',
        'retention_until',
        'deleted_at',
        'uploaded_by',
    ];

    protected $casts = [
        'retention_until' => 'date',
        'file_size_bytes' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function aiSuggestions(): HasMany
    {
        return $this->hasMany(AiSuggestion::class, 'source_id')
            ->where('source_type', 'document');
    }

    public function isDeletable(): bool
    {
        if (!$this->retention_until) {
            return true;
        }
    
        return Carbon::parse($this->retention_until)->isPast();
    }

    public function isProcessing(): bool
    {
        return $this->ocr_status === 'processing';
    }

    public function isReady(): bool
    {
        return $this->ocr_status === 'done';
    }
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Document $doc) {
            if ($doc->retention_until && $doc->retention_until->isFuture()) {
                throw new \RuntimeException(
                    'Document en période de rétention légale — suppression interdite jusqu\'au ' . $doc->retention_until->format('d/m/Y')
                );
            }
        });
    }
    protected static function booted(): void
{
    static::addGlobalScope('company', function (Builder $builder) {
        if (app()->has('currentCompany')) {
            $builder->where($builder->getModel()->getTable() . '.company_id', app('currentCompany')->id);
        }
    });
}
}