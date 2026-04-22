<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->enum('lock_type', ['date', 'entry']);
            $table->date('locked_until_date')->nullable();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['company_id', 'lock_type']);
            $table->index(['company_id', 'journal_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_locks');
    }
};
