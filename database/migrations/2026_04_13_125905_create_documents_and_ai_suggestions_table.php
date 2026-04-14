<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('file_name', 500);
            $table->string('mime_type', 100);
            $table->bigInteger('file_size_bytes');
            $table->text('storage_key');
            $table->string('document_type', 50); // invoice_pdf|supplier_bill|bank_statement
            $table->string('source', 30)->default('upload'); // upload|generated|export
            $table->enum('ocr_status', ['pending','processing','done','failed'])
                ->default('pending');
            $table->text('ocr_raw_text')->nullable();
            $table->date('retention_until')->nullable(); // upload_date + 10 years
            // Plain nullable timestamp — NOT SoftDeletes trait
            // App must check retention_until before allowing deletion
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('source_type', 30); // expense|bank_column|document
            $table->uuid('source_id')->nullable();
            $table->string('field_name', 100); // vendor_name, total_ht, etc.
            $table->text('suggested_value')->nullable();
            $table->decimal('confidence', 4, 3)->nullable(); // 0.000–1.000
            // null=pending, true=accepted, false=rejected — NEVER auto-set to true
            $table->boolean('accepted')->nullable();
            $table->text('final_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
        Schema::dropIfExists('documents');
    }
};