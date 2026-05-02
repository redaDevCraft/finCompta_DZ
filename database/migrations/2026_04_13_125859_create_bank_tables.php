<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_number', 100)->nullable();
            $table->char('currency', 3)->default('DZD');
            $table->foreignUuid('gl_account_id')->constrained('accounts'); // must be 512x
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('import_type', ['pdf_ocr','csv','excel','manual']);
            $table->string('source_document_path')->nullable();
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->integer('row_count')->default(0);
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts');
            $table->foreignUuid('import_id')
                ->nullable()
                ->constrained('bank_statement_imports')
                ->nullOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->text('label');
            $table->decimal('amount', 15, 2); // always positive
            $table->enum('direction', ['debit','credit']);
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->enum('reconcile_status', ['unmatched','matched','manually_posted','excluded'])
                ->default('unmatched');
            // journal_entry_id deferred FK — journal_entries already exists
            $table->foreignUuid('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();
            $table->foreignId('matched_by')->nullable()->constrained('users');
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'reconcile_status', 'transaction_date'], 'bt_company_reconcile_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('bank_accounts');
    }
};