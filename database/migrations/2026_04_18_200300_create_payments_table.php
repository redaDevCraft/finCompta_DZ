<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('gateway', 20)->default('chargily'); // chargily | bon_de_commande | manual
            $table->string('method', 20)->nullable();           // edahabia | cib | bank_transfer
            $table->string('billing_cycle', 10)->default('monthly');
            $table->integer('amount_dzd');
            $table->string('currency', 3)->default('DZD');
            // pending | processing | paid | failed | canceled | expired | refunded
            $table->string('status', 20)->default('pending');
            $table->string('checkout_id', 255)->nullable()->index();
            $table->string('checkout_url', 1024)->nullable();
            $table->string('bon_pdf_path', 1024)->nullable();
            $table->string('proof_upload_path', 1024)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
