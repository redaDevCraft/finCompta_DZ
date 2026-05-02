<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            // IMPORTANT: company is UUID to match companies.id
            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // users.id is bigint → keep foreignId here
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};