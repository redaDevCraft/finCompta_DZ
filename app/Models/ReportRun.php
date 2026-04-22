<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReportRun — one row per queued/completed report artifact.
 *
 * Status machine:
 *   queued  → job has been dispatched but a worker hasn't picked it up yet.
 *   running → worker is actively computing.
 *   ready   → artifact exists on disk, ready for download.
 *   failed  → worker errored; error_message holds the exception summary.
 *
 * Transitions are always forward (no ready→running). The controller never
 * flips status — only jobs do, inside try/catch so a thrown exception
 * doesn't leave a run stuck at running forever.
 */
class ReportRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const TYPE_BILAN_PDF = 'bilan_pdf';

    public const TYPE_VAT_XLSX = 'vat_xlsx';
    public const TYPE_ANALYTIC_TRIAL_BALANCE_XLSX = 'analytic_trial_balance_xlsx';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'user_id',
        'type',
        'params',
        'status',
        'storage_disk',
        'storage_path',
        'original_filename',
        'artifact_bytes',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'params' => 'array',
        'artifact_bytes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Same tenant-isolation pattern as every other model: when a
        // request is scoped to a company, every query is filtered
        // implicitly. Jobs running out-of-band (no currentCompany bound)
        // see all rows, which is what they need.
        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->has('currentCompany')) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    app('currentCompany')->id
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->storage_path !== null
            && $this->storage_disk !== null;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_FAILED], true);
    }
}
