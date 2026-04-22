<?php

declare(strict_types=1);

namespace App\Jobs\Reports;

use App\Exports\AnalyticTrialBalanceExport;
use App\Models\Company;
use App\Models\ReportRun;
use App\Services\AnalyticReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateAnalyticTrialBalanceXlsxJob implements ShouldQueue
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

    public function handle(AnalyticReportService $analyticReportService): void
    {
        $run = ReportRun::query()
            ->withoutGlobalScopes()
            ->find($this->reportRunId);

        if (! $run) {
            Log::warning('GenerateAnalyticTrialBalanceXlsxJob: report run missing', ['id' => $this->reportRunId]);

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
            $dateFrom = $params['date_from'] ?? null;
            $dateTo = $params['date_to'] ?? null;
            $axisId = $params['axis_id'] ?? null;
            $sectionId = $params['section_id'] ?? null;

            $data = $analyticReportService->buildTrialBalance(
                companyId: $company->id,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                axisId: $axisId,
                sectionId: $sectionId,
            );

            $filename = 'balance_analytique_'.now()->format('Ymd_His').'.xlsx';
            $relativePath = "reports/{$run->company_id}/{$run->id}/{$filename}";

            Excel::store(
                new AnalyticTrialBalanceExport($data['rows'], [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ]),
                $relativePath,
                'local'
            );

            if (! Storage::disk('local')->exists($relativePath)) {
                throw new \RuntimeException("Impossible d'écrire l'export de balance analytique.");
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
