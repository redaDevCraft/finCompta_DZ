<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('gateway', 32);
            $table->string('event_name', 120)->nullable();
            $table->string('signature_header', 500)->nullable();
            $table->uuid('payment_id')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['gateway', 'received_at']);
            $table->index(['payment_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};

