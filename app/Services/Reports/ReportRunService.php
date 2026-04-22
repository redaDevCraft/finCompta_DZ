<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * ReportRunService — thin factory for queued report artifacts.
 *
 * Why a service and not direct Model::create() in the controller:
 *   - Single place that stamps defaults (retention, params shape, user
 *     association) so every queued report is consistent regardless of
 *     which controller spawns it.
 *   - Decouples the controller from the job class: controllers call
 *     queue(..., type=BILAN_PDF) and the service maps it to the right
 *     job + queue name. When VAT / trial-balance / grand-livre follow,
 *     only this map changes.
 *   - Easy to unit-test. No static facade soup.
 */
final class ReportRunService
{
    /**
     * Default artifact retention. Beyond this the row is still visible
     * in the exports page but a future GC task will reclaim the file.
     */
    public const DEFAULT_RETENTION_DAYS = 7;

    /**
     * Map of report type → job class. Extending this wires a new
     * async report type without touching the controller.
     *
     * @var array<string, class-string>
     */
    private const JOB_MAP = [
        ReportRun::TYPE_BILAN_PDF => \App\Jobs\Reports\GenerateBilanPdfJob::class,
        ReportRun::TYPE_VAT_XLSX => \App\Jobs\Reports\GenerateVatXlsxJob::class,
        ReportRun::TYPE_ANALYTIC_TRIAL_BALANCE_XLSX => \App\Jobs\Reports\GenerateAnalyticTrialBalanceXlsxJob::class,
    ];

    /**
     * Queue name used for every report job. Isolated so a flood of
     * heavy bilan renders can't starve the latency-sensitive pdf /
     * ocr queues.
     */
    public const QUEUE_NAME = 'reports';

    /**
     * Create a queued row, dispatch the job, return the run.
     *
     * @param  array<string, mixed>  $params  Arbitrary inputs the job will
     *                                        consume (as_of_date, year, etc.)
     */
    public function queue(
        string $companyId,
        ?User $user,
        string $type,
        array $params = [],
    ): ReportRun {
        if (! isset(self::JOB_MAP[$type])) {
            throw new \InvalidArgumentException("Unknown report type: {$type}");
        }

        $run = ReportRun::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $user?->id,
            'type' => $type,
            'params' => $params,
            'status' => ReportRun::STATUS_QUEUED,
            'expires_at' => now()->addDays(self::DEFAULT_RETENTION_DAYS),
        ]);

        // afterCommit() ensures the worker can SELECT the row by the time
        // it picks up the job. Without it, a fast worker can race the
        // transaction and 404 on its own input.
        $jobClass = self::JOB_MAP[$type];
        $jobClass::dispatch($run->id)
            ->onQueue(self::QUEUE_NAME)
            ->afterCommit();

        return $run;
    }
}
