<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
DO $$
DECLARE
    check_name text;
BEGIN
    SELECT c.conname
    INTO check_name
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE t.relname = 'invoice_sequences'
      AND c.contype = 'c'
      AND pg_get_constraintdef(c.oid) LIKE '%document_type%';

    IF check_name IS NOT NULL THEN
        EXECUTE format('ALTER TABLE invoice_sequences DROP CONSTRAINT %I', check_name);
    END IF;

    ALTER TABLE invoice_sequences
        ADD CONSTRAINT invoice_sequences_document_type_check
        CHECK (document_type IN ('invoice','credit_note','quote','delivery_note','expense'));
END
$$;
SQL);
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('sequence_id')->nullable()->after('supplier_snapshot');
            $table->index('sequence_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['sequence_id']);
            $table->dropColumn('sequence_id');
        });
    }
};
