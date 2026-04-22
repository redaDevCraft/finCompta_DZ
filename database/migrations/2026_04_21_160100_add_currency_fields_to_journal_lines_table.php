<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignUuid('currency_id')->nullable()->after('analytic_section_id')->constrained('currencies')->nullOnDelete();
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_id');
            $table->decimal('amount_foreign_debit', 18, 4)->nullable()->after('exchange_rate');
            $table->decimal('amount_foreign_credit', 18, 4)->nullable()->after('amount_foreign_debit');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn(['exchange_rate', 'amount_foreign_debit', 'amount_foreign_credit']);
        });
    }
};
