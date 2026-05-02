<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->uuid('product_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(19);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
