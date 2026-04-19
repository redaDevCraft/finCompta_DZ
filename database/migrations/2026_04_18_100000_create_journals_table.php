<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('label', 120);
            $table->string('label_ar', 120)->nullable();
            $table->enum('type', ['sales', 'purchase', 'bank', 'cash', 'misc'])->default('misc');
            $table->foreignUuid('counterpart_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignUuid('journal_id')
                ->nullable()
                ->after('period_id')
                ->constrained('journals')
                ->nullOnDelete();

            $table->index(['company_id', 'journal_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['journal_id']);
            $table->dropIndex(['company_id', 'journal_id']);
            $table->dropColumn('journal_id');
        });

        Schema::dropIfExists('journals');
    }
};
