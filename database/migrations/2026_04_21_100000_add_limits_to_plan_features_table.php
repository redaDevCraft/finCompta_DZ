<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            if (! Schema::hasColumn('plan_features', 'limits')) {
                $table->json('limits')->nullable()->after('enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            if (Schema::hasColumn('plan_features', 'limits')) {
                $table->dropColumn('limits');
            }
        });
    }
};
