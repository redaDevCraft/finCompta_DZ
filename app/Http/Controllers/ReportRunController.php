<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints consumed by the Reports/Exports page and polling UIs.
 *
 *   GET  /reports/runs           → Inertia list of this tenant's runs.
 *   GET  /reports/runs/{id}      → Lightweight JSON status for polling.
 *   GET  /reports/runs/{id}/download → Streams the artifact; 404 if not
 *                                     ready (failed / queued / gone).
 *
 * Security model:
 *   - Global company scope filters every lookup here, so a user can
 *     never see / download another tenant's runs even if they guess the
 *     UUID.
 *   - Downloads stream through the controller rather than a public disk
 *     URL so tenancy enforcement runs on every byte, not just once at
 *     handshake.
 */
class ReportRunController extends Controller
{
    public function index(Request $request): Response
    {
        $runs = ReportRun::query()
            ->with('user:id,name')
            ->select([
                'id', 'company_id', 'user_id', 'type', 'params', 'status',
                'original_filename', 'artifact_bytes', 'error_message',
                'started_at', 'completed_at', 'created_at', 'expires_at',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (ReportRun $run) => $this->shapeRun($run));

        return Inertia::render('Reports/Exports', [
            'runs' => $runs,
        ]);
    }

    /**
     * Lightweight JSON for the polling loop on the exports page. Kept
     * deliberately small — no relations, no params blob — so it can be
     * called every few seconds without pressure on the DB.
     */
    public function show(ReportRun $reportRun): JsonResponse
    {
        return response()->json($this->shapeRun($reportRun));
    }

    public function download(ReportRun $reportRun): StreamedResponse
    {
        abort_unless($reportRun->isReady(), 404, 'Export non disponible.');

        $disk = Storage::disk($reportRun->storage_disk);

        abort_unless($disk->exists($reportRun->storage_path), 410, 'Artefact expiré ou supprimé.');

        $filename = $reportRun->original_filename ?? 'export.bin';

        // streamDownload + readStream keeps memory bounded for large
        // artifacts (no full file into PHP memory), and stays on the
        // Filesystem contract surface so the method resolves regardless
        // of concrete adapter type.
        return response()->streamDownload(
            function () use ($disk, $reportRun) {
                $stream = $disk->readStream($reportRun->storage_path);

                if ($stream === null) {
                    return;
                }

                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            $filename,
            [
                'Content-Type' => $this->guessMime($filename),
                'Content-Length' => (string) ($reportRun->artifact_bytes ?? $disk->size($reportRun->storage_path)),
            ],
        );
    }

    private function guessMime(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeRun(ReportRun $run): array
    {
        return [
            'id' => $run->id,
            'type' => $run->type,
            'status' => $run->status,
            'params' => $run->params,
            'original_filename' => $run->original_filename,
            'artifact_bytes' => $run->artifact_bytes,
            'error_message' => $run->error_message,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
            'expires_at' => $run->expires_at?->toIso8601String(),
            'user' => $run->user ? ['id' => $run->user->id, 'name' => $run->user->name] : null,
            'download_url' => $run->isReady()
                ? route('reports.runs.download', ['reportRun' => $run->id])
                : null,
        ];
    }
}
