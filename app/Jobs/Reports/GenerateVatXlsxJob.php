<?php

declare(strict_types=1);

namespace App\Jobs\Reports;

use App\Exports\VatReportExport;
use App\Models\Company;
use App\Models\ReportRun;
use App\Services\VatReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Builds TVA aggregates and writes an XLSX artifact off the HTTP thread.
 *
 * Heavy tenants can have large invoice/expense volumes; aggregating and
 * serialising Excel in-process would mirror the bilan PDF risk profile.
 */
class GenerateVatXlsxJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public string $reportRunId) {}

    public function handle(VatReportService $vatReportService): void
    {
        $run = ReportRun::query()
            ->withoutGlobalScopes()
            ->find($this->reportRunId);

        if (! $run) {
            Log::warning('GenerateVatXlsxJob: report run missing', ['id' => $this->reportRunId]);

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
            $params = $run->params ?? [];
            $year = (int) ($params['year'] ?? now()->year);
            $month = array_key_exists('month', $params) && $params['month'] !== null
                ? (int) $params['month']
                : null;
            $quarter = array_key_exists('quarter', $params) && $params['quarter'] !== null
                ? (int) $params['quarter']
                : null;

            $data = $vatReportService->buildForCompany($company->id, $year, $month, $quarter);

            $suffix = $vatReportService->exportFilenameSuffix($data['period']);
            $filename = "TVA_{$suffix}.xlsx";
            $relativePath = "reports/{$run->company_id}/{$run->id}/{$filename}";

            Excel::store(new VatReportExport($data), $relativePath, 'local');

            if (! Storage::disk('local')->exists($relativePath)) {
                throw new \RuntimeException("Impossible d'écrire l'export TVA.");
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
            $this->markFailed($run, $e->getMessage());

            throw $e;
        }
    }

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
