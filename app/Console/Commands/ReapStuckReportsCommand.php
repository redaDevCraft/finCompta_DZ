<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Flip orphaned `running` report rows to `failed`.
 *
 * Rationale: GenerateBilanPdfJob::handle() flips status to running, then
 * tries to flip it to ready/failed before returning. If the worker
 * process is killed between those two writes (OOM, SIGKILL, disk full,
 * container terminated mid-flight), the row is left at running forever.
 * The Laravel framework's failed() hook catches thrown exceptions but
 * not hard process termination.
 *
 * This reaper closes that gap by treating any row that's been running
 * longer than a configurable threshold (default: job's max timeout +
 * generous slack) as crashed, and flipping it to failed with a clear
 * error message so the user can retry from the UI.
 *
 * Uses the (company_id, status) index for the status filter; the
 * started_at range is applied post-lookup and is bounded by --limit.
 */
class ReapStuckReportsCommand extends Command
{
    protected $signature = 'reports:reap-stuck
        {--older-than=60 : Minutes of "running" before a run is considered stuck}
        {--limit=200 : Maximum number of rows to process in this run}
        {--dry-run : List what would be reaped without mutating anything}';

    protected $description = 'Flip orphaned `running` report runs to `failed` after a timeout';

    public function handle(): int
    {
        $olderThanMinutes = max(1, (int) $this->option('older-than'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subMinutes($olderThanMinutes);

        $runs = ReportRun::query()
            ->withoutGlobalScopes()
            ->where('status', ReportRun::STATUS_RUNNING)
            // started_at can be null if the worker was killed between
            // the transition write and its commit. Treat null as stuck
            // too, using created_at as the fallback clock.
            ->where(function ($q) use ($cutoff) {
                $q->where('started_at', '<', $cutoff)
                    ->orWhere(function ($qq) use ($cutoff) {
                        $qq->whereNull('started_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->orderBy('started_at')
            ->limit($limit)
            ->get();

        if ($runs->isEmpty()) {
            $this->info("No stuck runs older than {$olderThanMinutes} min.");

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%s %d stuck run(s) (older than %d min).',
            $dryRun ? '[dry-run] would reap' : 'Reaping',
            $runs->count(),
            $olderThanMinutes,
        ));

        $reaped = 0;

        /** @var ReportRun $run */
        foreach ($runs as $run) {
            if ($dryRun) {
                $this->line("  - {$run->id}  type={$run->type}  started_at=".($run->started_at ?? 'null'));
                $reaped++;

                continue;
            }

            $run->forceFill([
                'status' => ReportRun::STATUS_FAILED,
                'error_message' => "Worker crash suspected — run reaped after {$olderThanMinutes} min stuck in running state.",
                'completed_at' => now(),
            ])->save();

            $reaped++;
        }

        $this->info(sprintf(
            '%s %d run(s).',
            $dryRun ? '[dry-run] would reap' : 'Reaped',
            $reaped,
        ));

        Log::info('reports:reap-stuck complete', [
            'dry_run' => $dryRun,
            'older_than_minutes' => $olderThanMinutes,
            'reaped' => $reaped,
        ]);

        return self::SUCCESS;
    }
}
