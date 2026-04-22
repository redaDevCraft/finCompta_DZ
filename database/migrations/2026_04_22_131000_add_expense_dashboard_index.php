<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX IF NOT EXISTS expenses_company_status_date_idx
            ON expenses (company_id, status, expense_date)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS expenses_company_status_date_idx');
    }
};
