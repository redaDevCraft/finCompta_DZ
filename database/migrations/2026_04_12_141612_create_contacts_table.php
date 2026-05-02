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
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', ['client','supplier','both']);
            $table->enum('entity_type', ['individual','enterprise'])->default('individual');

            $table->string('display_name');
            $table->string('raison_sociale')->nullable();
            $table->string('nif', 30)->nullable();
            $table->string('nis', 30)->nullable();
            $table->string('rc', 50)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_wilaya', 100)->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
