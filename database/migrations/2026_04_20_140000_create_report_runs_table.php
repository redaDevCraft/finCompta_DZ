<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * report_runs — artifact-oriented async export pipeline.
 *
 * Every heavy report (bilan PDF, VAT export, trial balance, etc.) stops
 * running inline on the HTTP thread. Instead:
 *
 *   1. User clicks "Exporter PDF" → controller inserts a row here
 *      (status=queued), dispatches a job to the reports queue, returns a
 *      run id.
 *   2. Worker picks up the job → flips status to running → computes data
 *      + renders artifact → writes it to the local disk → flips status
 *      to ready (or failed with error message).
 *   3. User polls /reports/runs/{id} (JSON) until ready, then downloads
 *      from /reports/runs/{id}/download which streams the file back
 *      through the controller for tenant-scoped authorisation.
 *
 * Indexes:
 *   - (company_id, created_at desc)  → list-my-exports page
 *   - (company_id, status)           → "any queued/running for me?" check
 *   - (expires_at)                   → future GC sweep
 *
 * params is stored as jsonb so we can replay a failed run with the same
 * inputs without remembering the URL, and so we can surface the original
 * request parameters on the exports page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            // users.id is a plain bigint auto-increment in this project —
            // do not assume uuid FK parity elsewhere in the codebase.
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('type', 64);
            $table->json('params')->nullable();

            $table->string('status', 16)->default('queued');
            $table->string('storage_disk', 32)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->unsignedBigInteger('artifact_bytes')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index(['company_id', 'created_at'], 'report_runs_company_created_idx');
            $table->index(['company_id', 'status'], 'report_runs_company_status_idx');
            $table->index('expires_at', 'report_runs_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
