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
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('label');
            $table->string('label_ar')->nullable();
            $table->smallInteger('class'); // 1-7 per Algerian SCF
            $table->enum('type', [
                'asset','liability','equity','revenue','expense',
                'vat_collected','vat_deductible'
            ]);
            $table->boolean('is_system')->default(false); // system accounts cannot be deleted
            $table->string('parent_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
