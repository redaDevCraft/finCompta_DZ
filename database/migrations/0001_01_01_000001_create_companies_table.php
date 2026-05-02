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
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('raison_sociale');
            $table->string('forme_juridique', 50);
            $table->string('nif', 30)->unique();
            $table->string('nis', 30)->unique();
            $table->string('rc', 50);
            $table->string('ai', 50)->nullable();
            $table->string('address_line1');
            $table->string('address_wilaya', 100);
            $table->enum('tax_regime', ['IBS', 'IRG', 'IFU'])->default('IBS');
            $table->boolean('vat_registered')->default(true);
            $table->smallInteger('fiscal_year_end')->default(12);
            $table->char('currency', 3)->default('DZD');
            $table->enum('status', ['active', 'suspended', 'archived'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
