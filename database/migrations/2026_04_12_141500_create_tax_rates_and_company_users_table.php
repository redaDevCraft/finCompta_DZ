<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // company_users — needed for multi-tenant role access
        Schema::create('company_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner','accountant','viewer'])->default('owner');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->unique(['company_id', 'user_id']);
        });

        // tax_rates — required by invoice_lines and expense_lines
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('label', 100);           // 'TVA 19%', 'TVA 9%', 'Exonéré'
            $table->decimal('rate_percent', 5, 2);
            $table->string('tax_type', 20)->default('TVA');
            $table->boolean('is_recoverable')->default(true);
            $table->string('reporting_code', 20)->nullable(); // G50 line ref
            $table->date('effective_from');
            $table->date('effective_to')->nullable();         // NULL = still in force
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('company_users');
    }
};