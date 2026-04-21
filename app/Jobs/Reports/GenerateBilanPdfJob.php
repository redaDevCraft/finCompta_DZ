<?php

declare(strict_types=1);

namespace App\Jobs\Reports;

use App\Models\Company;
use App\Models\ReportRun;
use App\Services\BilanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders a bilan PDF off the HTTP request thread and persists the
 * artifact to local storage for later download.
 *
 * Why this is a job, not inline:
 *   - BilanService::compute scans every posted journal line up to the
 *     as_of_date. On a large company with 5+ years of history and
 *     100k+ lines, this is tens of seconds.
 *   - Dompdf is a CPU- and memory-heavy process. Running it on the
 *     PHP-FPM worker holds a connection, burns RAM, and can hit the
 *     request timeout.
 *   - Queueing it moves the work to a dedicated `reports` queue so
 *     invoice PDFs and OCR (which have their own queues) aren't
 *     blocked by heavy reports.
 *
 * Failure semantics:
 *   - Up to 2 retries with exponential backoff; after that the run is
 *     flipped to failed with the exception message surfaced on the
 *     exports page.
 *   - Any exception inside handle() is caught so the status doesn't
 *     stay stuck at `running`. The framework still bubbles it so
 *     retries and failed() get called.
 */
class GenerateBilanPdfJob implements ShouldQueue
{
    use Queueable;

    /**
     * Reasonably generous timeout — bilan compute + dompdf can exceed
     * default worker timeouts on heavy tenants.
     */
    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @return array<int,int>  exponential backoff in seconds
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public string $reportRunId) {}

    public function handle(BilanService $bilanService): void
    {
        // No global company scope here — workers run out-of-band.
        $run = ReportRun::query()
            ->withoutGlobalScopes()
            ->find($this->reportRunId);

        if (! $run) {
            Log::warning('GenerateBilanPdfJob: report run missing', ['id' => $this->reportRunId]);

            return;
        }

        if ($run->status !== ReportRun::STATUS_QUEUED && $run->status !== ReportRun::STATUS_RUNNING) {
            return;
        }

        $company = Company::query()->withoutGlobalScopes()->find($run->company_id);

        if (! $company) {
            $this->markFailed($run, 'Société introuvable.');

            return;
        }

        $run->forceFill([
            'status' => ReportRun::STATUS_RUNNING,
            'started_at' => now(),
        ])->save();

        try {
            $asOf = (string) ($run->params['as_of_date'] ?? now()->endOfYear()->toDateString());

            $bilan = $bilanService->compute($company, $asOf);

            $pdf = Pdf::loadView('pdf.bilan', ['bilan' => $bilan])->setPaper('a4');

            $filename = "bilan_{$asOf}.pdf";
            $relativePath = "reports/{$run->company_id}/{$run->id}/{$filename}";

            $stored = Storage::disk('local')->put($relativePath, $pdf->output());

            if ($stored === false) {
                throw new \RuntimeException('Impossible d\'écrire l\'artefact PDF.');
            }

            $run->forceFill([
                'status' => ReportRun::STATUS_READY,
                'storage_disk' => 'local',
                'storage_path' => $relativePath,
                'original_filename' => $filename,
                'artifact_bytes' => Storage::disk('local')->size($relativePath),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $e) {
            // Surface the failure on the row so the UI can show it, but
            // rethrow so the framework still counts the attempt and
            // reschedules if tries remain.
            $this->markFailed($run, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Called by the framework once all retries are exhausted. At this
     * point `failed` is the terminal state — no rescheduling.
     */
    public function failed(Throwable $e): void
    {
        $run = ReportRun::query()
            ->withoutGlobalScopes()
            ->find($this->reportRunId);

        if ($run && ! $run->isTerminal()) {
            $this->markFailed($run, $e->getMessage());
        }
    }

    private function markFailed(ReportRun $run, string $message): void
    {
        $run->forceFill([
            'status' => ReportRun::STATUS_FAILED,
            'error_message' => mb_substr($message, 0, 1000),
            'completed_at' => now(),
        ])->save();
    }
}
