<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('feature_key', 100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->cascadeOnDelete();

            $table->unique(['plan_id', 'feature_key']);
            $table->index(['plan_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};

