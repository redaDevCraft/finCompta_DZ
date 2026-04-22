<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_user_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_post')->default(false);
            $table->timestamps();

            $table->unique(['journal_id', 'user_id']);
            $table->index(['user_id', 'can_view']);
            $table->index(['user_id', 'can_post']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_user_permissions');
    }
};
