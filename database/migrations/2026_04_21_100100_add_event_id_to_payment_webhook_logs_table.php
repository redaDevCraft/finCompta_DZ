<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhook_logs', function (Blueprint $table) {
            $table->string('event_id', 255)->nullable()->after('gateway');
            $table->boolean('is_duplicate')->default(false)->after('signature_valid');
            $table->index(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_logs', function (Blueprint $table) {
            $table->dropIndex(['gateway', 'event_id']);
            $table->dropColumn(['event_id', 'is_duplicate']);
        });
    }
};
