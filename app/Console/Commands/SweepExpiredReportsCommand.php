<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Delete expired report artifacts + rows.
 *
 * Scope: rows whose status is terminal (ready | failed) AND whose
 * expires_at has passed. Non-terminal rows are never swept — a worker
 * might still be working on them, and the ReapStuckReportsCommand
 * handles the genuinely-stuck ones separately.
 *
 * Bounded by --limit per invocation so a pathological backlog can't
 * make one sweep lock the table forever. Rows that fail to process
 * (e.g. storage driver unreachable) are logged but do not abort the
 * batch — the next run will try them again.
 *
 * Leans on the (expires_at) index added in the Phase-6 migration: the
 * WHERE is sargable, no full scan.
 */
class SweepExpiredReportsCommand extends Command
{
    protected $signature = 'reports:sweep
        {--limit=500 : Maximum number of rows to process in this run}
        {--dry-run : List what would be deleted without mutating anything}';

    protected $description = 'Delete expired report artifacts and their DB rows';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $started = microtime(true);

        // withoutGlobalScopes: scheduled jobs run outside any tenant
        // context. The sweep is cross-tenant by design.
        $runs = ReportRun::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [ReportRun::STATUS_READY, ReportRun::STATUS_FAILED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();

        if ($runs->isEmpty()) {
            $this->info('Nothing to sweep.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%s %d expired run(s) found (limit=%d).',
            $dryRun ? '[dry-run]' : 'Sweeping',
            $runs->count(),
            $limit,
        ));

        $artifactsDeleted = 0;
        $rowsDeleted = 0;
        $bytesReclaimed = 0;
        $errors = 0;

        /** @var ReportRun $run */
        foreach ($runs as $run) {
            try {
                if ($run->storage_disk && $run->storage_path) {
                    $disk = Storage::disk($run->storage_disk);

                    if ($disk->exists($run->storage_path)) {
                        if (! $dryRun) {
                            $disk->delete($run->storage_path);
                        }

                        $artifactsDeleted++;
                        $bytesReclaimed += (int) ($run->artifact_bytes ?? 0);
                    }
                }

                if (! $dryRun) {
                    $run->delete();
                }

                $rowsDeleted++;
            } catch (Throwable $e) {
                $errors++;
                // Log but don't throw — one poisoned row shouldn't block
                // the rest of the batch.
                Log::warning('reports:sweep failed on run', [
                    'run_id' => $run->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  ! run {$run->id}: ".$e->getMessage());
            }
        }

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        $this->info(sprintf(
            '%s %d row(s), %d artifact(s), %s reclaimed, %d error(s) in %d ms.',
            $dryRun ? '[dry-run] would sweep' : 'Swept',
            $rowsDeleted,
            $artifactsDeleted,
            $this->humanBytes($bytesReclaimed),
            $errors,
            $elapsedMs,
        ));

        // Emit a structured log line so ops pipelines can alert on
        // anomalous sweep volumes or error counts.
        Log::info('reports:sweep complete', [
            'dry_run' => $dryRun,
            'rows_deleted' => $rowsDeleted,
            'artifacts_deleted' => $artifactsDeleted,
            'bytes_reclaimed' => $bytesReclaimed,
            'errors' => $errors,
            'elapsed_ms' => $elapsedMs,
        ]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2).' MB';
        }

        return round($bytes / 1024 / 1024 / 1024, 2).' GB';
    }
}
