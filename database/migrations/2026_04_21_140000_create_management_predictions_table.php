<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('management_predictions_enabled')
                ->default(false)
                ->after('period_lock_password_hash');
        });

        Schema::create('management_predictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignUuid('analytic_section_id')->nullable()->constrained('analytic_sections')->nullOnDelete();
            $table->enum('period_type', ['month', 'quarter', 'year']);
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->decimal('amount', 15, 2);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'period_start_date', 'period_end_date'], 'mgmt_pred_company_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_predictions');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('management_predictions_enabled');
        });
    }
};
