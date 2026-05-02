<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('tagline')->nullable();
            $table->integer('monthly_price_dzd')->default(0);
            $table->integer('yearly_price_dzd')->default(0);
            $table->integer('trial_days')->default(3);
            $table->jsonb('features')->nullable();
            $table->integer('max_users')->nullable();
            $table->integer('max_invoices_per_month')->nullable();
            $table->integer('max_documents_per_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
