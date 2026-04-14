<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
            // json() maps to JSONB on Postgres, JSON on SQLite
            $table->json('supplier_snapshot')->nullable();
            $table->string('reference', 100)->nullable();
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_ht', 15, 2);
            $table->decimal('total_vat', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2);
            // account_id FK deferred — accounts table created in migration 010
            $table->uuid('account_id')->nullable();
            $table->enum('status', ['draft','confirmed','paid','cancelled'])
                ->default('draft');
            // source_document_id FK deferred — documents table created later
            $table->uuid('source_document_id')->nullable();
            // AI suggestions NEVER auto-set this to true
            $table->boolean('ai_extracted')->default(false);
            // journal_entry_id FK deferred — journal_entries created later
            $table->uuid('journal_entry_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expense_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->text('designation');
            $table->decimal('amount_ht', 15, 2);
            $table->decimal('vat_rate_pct', 5, 2)->default(0);
            $table->decimal('amount_vat', 15, 2)->default(0);
            $table->decimal('amount_ttc', 15, 2);
            $table->foreignUuid('tax_rate_id')
                ->nullable()
                ->constrained('tax_rates')
                ->nullOnDelete();
            // account_id FK deferred — accounts table created in migration 010
            $table->uuid('account_id')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
        Schema::dropIfExists('expenses');
    }
};