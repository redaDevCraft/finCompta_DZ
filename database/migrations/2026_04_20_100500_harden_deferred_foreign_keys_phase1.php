<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — FK hardening for historically deferred columns.
 *
 * The following uuid columns exist in the schema but never received FK
 * constraints because the parent tables were created later:
 *   - invoices.journal_entry_id        → journal_entries.id
 *   - invoice_lines.account_id         → accounts.id
 *   - expenses.account_id              → accounts.id
 *   - expenses.source_document_id      → documents.id
 *   - expenses.journal_entry_id        → journal_entries.id
 *   - expense_lines.account_id         → accounts.id
 *
 * Safety rules applied here:
 *   - Skipped entirely on SQLite: adding FKs via ALTER TABLE is not safely
 *     supported; SQLite is used locally only and does not need this hardening.
 *   - Orphan rows are NULLed before FK creation to avoid migration failure.
 *   - Each FK creation is guarded so the migration is idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite cannot add FK constraints to an existing table through
            // ALTER TABLE in a safe way. Leave hardening for real databases.
            return;
        }

        $this->attachNullableForeignKey(
            table: 'invoices',
            column: 'journal_entry_id',
            on: 'journal_entries',
            name: 'invoices_journal_entry_id_foreign',
        );

        $this->attachNullableForeignKey(
            table: 'invoice_lines',
            column: 'account_id',
            on: 'accounts',
            name: 'invoice_lines_account_id_foreign',
        );

        $this->attachNullableForeignKey(
            table: 'expenses',
            column: 'account_id',
            on: 'accounts',
            name: 'expenses_account_id_foreign',
        );

        $this->attachNullableForeignKey(
            table: 'expenses',
            column: 'source_document_id',
            on: 'documents',
            name: 'expenses_source_document_id_foreign',
        );

        $this->attachNullableForeignKey(
            table: 'expenses',
            column: 'journal_entry_id',
            on: 'journal_entries',
            name: 'expenses_journal_entry_id_foreign',
        );

        $this->attachNullableForeignKey(
            table: 'expense_lines',
            column: 'account_id',
            on: 'accounts',
            name: 'expense_lines_account_id_foreign',
        );
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $map = [
            'invoices' => ['invoices_journal_entry_id_foreign'],
            'invoice_lines' => ['invoice_lines_account_id_foreign'],
            'expenses' => [
                'expenses_account_id_foreign',
                'expenses_source_document_id_foreign',
                'expenses_journal_entry_id_foreign',
            ],
            'expense_lines' => ['expense_lines_account_id_foreign'],
        ];

        foreach ($map as $table => $keys) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($keys) {
                foreach ($keys as $key) {
                    try {
                        $t->dropForeign($key);
                    } catch (\Throwable) {
                        // foreign key was not present; ignore
                    }
                }
            });
        }
    }

    /**
     * Validate data, then add a nullable FK (ON DELETE SET NULL) if missing.
     *
     * Orphan rows are nullified before FK creation so the migration does not
     * fail on stale data; that matches the semantics we want for these
     * historically deferred, nullable links.
     */
    private function attachNullableForeignKey(
        string $table,
        string $column,
        string $on,
        string $name,
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasTable($on)) {
            return;
        }

        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->foreignKeyExists($table, $name)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, DB::table($on)->select('id'))
            ->update([$column => null]);

        Schema::table($table, function (Blueprint $t) use ($column, $on, $name) {
            $t->foreign($column, $name)
                ->references('id')
                ->on($on)
                ->nullOnDelete();
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        try {
            $foreignKeys = Schema::getForeignKeys($table);
        } catch (\Throwable) {
            return false;
        }

        foreach ($foreignKeys as $fk) {
            if (($fk['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
