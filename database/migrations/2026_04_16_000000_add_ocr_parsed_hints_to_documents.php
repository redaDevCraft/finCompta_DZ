<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->json('ocr_parsed_hints')
                ->nullable()
                ->after('ocr_raw_text');

            $table->text('ocr_error')
                ->nullable()
                ->after('ocr_parsed_hints');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['ocr_parsed_hints', 'ocr_error']);
        });
    }
};
