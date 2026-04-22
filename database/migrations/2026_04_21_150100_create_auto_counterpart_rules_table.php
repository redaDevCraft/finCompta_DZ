<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_counterpart_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignUuid('trigger_account_id')->constrained('accounts');
            $table->enum('trigger_direction', ['debit', 'credit']);
            $table->foreignUuid('counterpart_account_id')->constrained('accounts');
            $table->enum('counterpart_direction', ['debit', 'credit']);
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'trigger_account_id', 'trigger_direction', 'priority'], 'ac_rules_trigger_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_counterpart_rules');
    }
};
