<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_payment_terms_days')->nullable()->after('phone');
            $table->string('default_payment_mode', 100)->nullable()->after('default_payment_terms_days');
            $table->uuid('default_expense_account_id')->nullable()->after('default_payment_mode');
            $table->uuid('default_tax_rate_id')->nullable()->after('default_expense_account_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->text('invoice_default_notes')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('invoice_default_notes');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'default_payment_terms_days',
                'default_payment_mode',
                'default_expense_account_id',
                'default_tax_rate_id',
            ]);
        });
    }
};
