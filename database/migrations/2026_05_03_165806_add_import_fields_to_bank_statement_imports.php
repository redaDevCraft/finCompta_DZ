<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_statement_imports', function (Blueprint $table) {
            $table->string('file_name')->nullable()->after('import_type');
            $table->string('file_path')->nullable()->after('file_name');
            $table->string('mime_type', 100)->nullable()->after('file_path');
            $table->foreignUuid('document_id')
                ->nullable()
                ->after('mime_type')
                ->constrained('documents')
                ->nullOnDelete();
            $table->string('status', 30)
                ->default('uploaded')
                ->after('document_id');
                // values: uploaded|processing|awaiting_mapping|imported|failed
            $table->json('meta')->nullable()->after('status');
    
            // period_start/period_end were NOT NULLABLE before — make them nullable
            // to support imports where the user doesn't know the period upfront
            $table->date('period_start')->nullable()->change();
            $table->date('period_end')->nullable()->change();
    
            // imported_by was required — make nullable for API/system imports
            $table->foreignId('imported_by')->nullable()->change();
        });
    }
};
