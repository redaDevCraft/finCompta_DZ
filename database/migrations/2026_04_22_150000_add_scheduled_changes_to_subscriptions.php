<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignUuid('next_plan_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('plans')
                ->nullOnDelete();
            $table->string('next_billing_cycle', 10)->nullable()->after('billing_cycle');
            $table->timestamp('next_change_effective_at')->nullable()->after('current_period_ends_at');
            $table->string('pending_change_reason', 30)->nullable()->after('next_change_effective_at');
            $table->timestamp('pending_change_requested_at')->nullable()->after('pending_change_reason');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_plan_id');
            $table->dropColumn([
                'next_billing_cycle',
                'next_change_effective_at',
                'pending_change_reason',
                'pending_change_requested_at',
            ]);
        });
    }
};
