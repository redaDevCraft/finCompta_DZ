<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year');
            $table->smallInteger('month'); // 1-12
            $table->enum('status', ['open','review','locked'])->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->unique(['company_id', 'year', 'month']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('period_id')->constrained('fiscal_periods');
            $table->date('entry_date');
            $table->string('journal_code', 10); // VT=sales, AC=purchases, BQ=bank, CA=cash, OD=misc
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft','posted','reversed'])->default('draft');
            $table->string('source_type', 30)->nullable(); // invoice|expense|bank_txn
            $table->uuid('source_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['company_id', 'period_id', 'journal_code']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts');
            $table->text('description')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // CHECK constraint for journal_lines (safe for Postgres/SQLite)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT chk_debit_credit CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('fiscal_periods');
    }
};