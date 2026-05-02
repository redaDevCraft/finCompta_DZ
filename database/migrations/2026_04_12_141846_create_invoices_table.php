<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sequence_id')->constrained('invoice_sequences');
            $table->string('invoice_number', 50);
            $table->enum('document_type', ['invoice','credit_note','quote','delivery_note']);
            $table->enum('status', ['draft','issued','partially_paid','paid','voided','replaced'])->default('draft');
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->jsonb('client_snapshot')->nullable(); // frozen at issuance
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('payment_mode', 50)->nullable();
            $table->char('currency', 3)->default('DZD');
            $table->decimal('subtotal_ht', 15, 2)->default(0);
            $table->decimal('total_vat', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('original_invoice_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->string('pdf_path')->nullable();
            $table->foreignUuid('journal_entry_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'status', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
