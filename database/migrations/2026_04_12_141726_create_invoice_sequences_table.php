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
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));

            $table->foreignUuid('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('document_type', ['invoice','credit_note','quote','delivery_note']);
            $table->smallInteger('fiscal_year');

            $table->string('prefix', 20)->nullable(); // FAC, AV, NC, BL

            $table->integer('last_number')->default(0);
            $table->integer('total_issued')->default(0);
            $table->integer('total_voided')->default(0);

            $table->boolean('locked')->default(false);

            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
