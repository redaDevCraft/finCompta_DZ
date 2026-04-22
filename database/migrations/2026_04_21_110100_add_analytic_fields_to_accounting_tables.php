<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignUuid('default_analytic_section_id')
                ->nullable()
                ->after('is_lettrable')
                ->constrained('analytic_sections')
                ->nullOnDelete();
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignUuid('analytic_section_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('analytic_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analytic_section_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_analytic_section_id');
        });
    }
};
