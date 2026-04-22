<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytic_axes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('analytic_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('analytic_axis_id')->constrained('analytic_axes')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'analytic_axis_id', 'code'], 'analytic_sections_company_axis_code_unique');
            $table->index(['company_id', 'analytic_axis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytic_sections');
        Schema::dropIfExists('analytic_axes');
    }
};
