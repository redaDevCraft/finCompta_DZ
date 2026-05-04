<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    // Step 1: delete orphaned entries (journal_id is null or points to deleted journal)
    DB::statement("
        DELETE FROM journal_lines
        WHERE journal_entry_id IN (
            SELECT id FROM journal_entries
            WHERE journal_id IS NULL
               OR journal_id NOT IN (SELECT id FROM journals)
        )
    ");

    DB::statement("
        DELETE FROM journal_entries
        WHERE journal_id IS NULL
           OR journal_id NOT IN (SELECT id FROM journals)
    ");

    // Step 2: drop old nullable FK
    Schema::table('journal_entries', function (Blueprint $table) {
        $table->dropForeign(['journal_id']);
    });

    // Step 3: make NOT NULL + cascade on delete
    Schema::table('journal_entries', function (Blueprint $table) {
        $table->uuid('journal_id')->nullable(false)->change();
        $table->foreign('journal_id')
            ->references('id')
            ->on('journals')
            ->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::table('journal_entries', function (Blueprint $table) {
        $table->dropForeign(['journal_id']);
        $table->uuid('journal_id')->nullable()->change();
        $table->foreign('journal_id')
            ->references('id')
            ->on('journals')
            ->nullOnDelete();
    });
}
};
