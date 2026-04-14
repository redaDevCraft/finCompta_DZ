<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_vat_buckets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('tax_rate_id')
                ->constrained('tax_rates');
            $table->decimal('rate_pct', 5, 2);
            $table->decimal('base_ht', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->unique(['invoice_id', 'tax_rate_id']);
        });
        
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->text('designation'); // HARD BLOCK if empty on application side
            $table->decimal('quantity', 12, 4)->default(1);
            $table->string('unit', 30)->nullable(); // pièce, heure, kg, m²
            $table->decimal('unit_price_ht', 15, 4);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('line_ht', 15, 2); // computed: qty * pu * (1 - disc/100)
            $table->foreignUuid('tax_rate_id')
                ->nullable()
                ->constrained('tax_rates')
                ->nullOnDelete();
            // Snapshot of the rate at save time — intentionally not a live FK value
            $table->decimal('vat_rate_pct', 5, 2)->default(0);
            $table->decimal('line_vat', 15, 2)->default(0);
            $table->decimal('line_ttc', 15, 2);
            // account_id FK deferred — accounts table created in migration 010
            $table->uuid('account_id')->nullable();
            $table->timestamps();
        });

       
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_vat_buckets');
        Schema::dropIfExists('invoice_lines');
    }
};