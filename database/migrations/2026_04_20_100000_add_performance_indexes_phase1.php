<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — performance index wave.
 *
 * Additive only. No column renames, no FK changes. Each index creation is
 * guarded by Schema::hasIndex() so this migration is safe to re-run on
 * databases that already carry some of these indexes.
 *
 * Target query shapes (from hot-path audit):
 *   - tenant-scoped list endpoints: (company_id, status/date, id)
 *   - tenant-scoped filter/sort: (company_id, <filter>), (company_id, created_at, id)
 *   - relation loads: (<fk>) and (<fk>, <secondary>)
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | contacts
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('contacts', ['company_id', 'is_active'], 'contacts_company_active_idx');
        $this->safeIndex('contacts', ['company_id', 'display_name'], 'contacts_company_name_idx');

        /*
        |--------------------------------------------------------------------------
        | invoices
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('invoices', ['company_id', 'created_at', 'id'], 'invoices_company_created_idx');
        $this->safeIndex('invoices', ['company_id', 'contact_id'], 'invoices_company_contact_idx');
        $this->safeIndex('invoices', ['company_id', 'document_type', 'issue_date'], 'invoices_company_doctype_date_idx');
        $this->safeIndex('invoices', ['journal_entry_id'], 'invoices_journal_entry_idx');
        $this->safeIndex('invoices', ['original_invoice_id'], 'invoices_original_invoice_idx');

        /*
        |--------------------------------------------------------------------------
        | invoice_lines
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('invoice_lines', ['invoice_id', 'sort_order'], 'invoice_lines_invoice_sort_idx');
        $this->safeIndex('invoice_lines', ['account_id'], 'invoice_lines_account_idx');

        /*
        |--------------------------------------------------------------------------
        | expenses
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('expenses', ['company_id', 'status', 'expense_date'], 'expenses_company_status_date_idx');
        $this->safeIndex('expenses', ['company_id', 'created_at', 'id'], 'expenses_company_created_idx');
        $this->safeIndex('expenses', ['company_id', 'contact_id'], 'expenses_company_contact_idx');
        $this->safeIndex('expenses', ['source_document_id'], 'expenses_source_document_idx');
        $this->safeIndex('expenses', ['journal_entry_id'], 'expenses_journal_entry_idx');
        $this->safeIndex('expenses', ['account_id'], 'expenses_account_idx');

        /*
        |--------------------------------------------------------------------------
        | expense_lines
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('expense_lines', ['expense_id', 'sort_order'], 'expense_lines_expense_sort_idx');
        $this->safeIndex('expense_lines', ['account_id'], 'expense_lines_account_idx');

        /*
        |--------------------------------------------------------------------------
        | fiscal_periods
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('fiscal_periods', ['company_id', 'status'], 'fiscal_periods_company_status_idx');

        /*
        |--------------------------------------------------------------------------
        | journal_entries
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('journal_entries', ['company_id', 'status', 'entry_date'], 'journal_entries_company_status_date_idx');
        $this->safeIndex('journal_entries', ['company_id', 'created_at', 'id'], 'journal_entries_company_created_idx');
        $this->safeIndex('journal_entries', ['source_type', 'source_id'], 'journal_entries_source_idx');
        $this->safeIndex('journal_entries', ['period_id'], 'journal_entries_period_idx');

        /*
        |--------------------------------------------------------------------------
        | journal_lines
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('journal_lines', ['journal_entry_id'], 'journal_lines_journal_entry_idx');
        $this->safeIndex('journal_lines', ['account_id', 'journal_entry_id'], 'journal_lines_account_entry_idx');
        $this->safeIndex('journal_lines', ['contact_id'], 'journal_lines_contact_idx');

        /*
        |--------------------------------------------------------------------------
        | bank_transactions
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('bank_transactions', ['bank_account_id', 'transaction_date'], 'bank_tx_account_date_idx');
        $this->safeIndex('bank_transactions', ['import_id'], 'bank_tx_import_idx');
        $this->safeIndex('bank_transactions', ['journal_entry_id'], 'bank_tx_journal_entry_idx');
        $this->safeIndex('bank_transactions', ['company_id', 'transaction_date'], 'bank_tx_company_date_idx');

        /*
        |--------------------------------------------------------------------------
        | bank_statement_imports
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('bank_statement_imports', ['company_id', 'created_at'], 'bank_imports_company_created_idx');
        $this->safeIndex('bank_statement_imports', ['bank_account_id', 'period_start', 'period_end'], 'bank_imports_account_period_idx');

        /*
        |--------------------------------------------------------------------------
        | documents
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('documents', ['company_id', 'created_at', 'id'], 'documents_company_created_idx');
        $this->safeIndex('documents', ['company_id', 'ocr_status', 'created_at'], 'documents_company_ocr_status_idx');
        $this->safeIndex('documents', ['company_id', 'document_type', 'created_at'], 'documents_company_doctype_idx');
        $this->safeIndex('documents', ['retention_until'], 'documents_retention_idx');

        /*
        |--------------------------------------------------------------------------
        | ai_suggestions
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('ai_suggestions', ['company_id', 'source_type', 'source_id'], 'ai_suggestions_source_idx');
        $this->safeIndex('ai_suggestions', ['company_id', 'accepted', 'created_at'], 'ai_suggestions_accepted_idx');

        /*
        |--------------------------------------------------------------------------
        | letterings
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('letterings', ['company_id', 'matched_at'], 'letterings_company_matched_idx');
        $this->safeIndex('letterings', ['contact_id', 'matched_at'], 'letterings_contact_matched_idx');

        /*
        |--------------------------------------------------------------------------
        | accounts
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('accounts', ['company_id', 'class', 'is_active'], 'accounts_company_class_active_idx');
        $this->safeIndex('accounts', ['company_id', 'is_lettrable'], 'accounts_company_lettrable_idx');

        /*
        |--------------------------------------------------------------------------
        | tax_rates
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('tax_rates', ['company_id', 'is_active'], 'tax_rates_company_active_idx');

        /*
        |--------------------------------------------------------------------------
        | subscriptions
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('subscriptions', ['company_id', 'created_at', 'id'], 'subscriptions_company_created_idx');
        $this->safeIndex('subscriptions', ['status', 'current_period_ends_at'], 'subscriptions_status_period_end_idx');
        $this->safeIndex('subscriptions', ['plan_id'], 'subscriptions_plan_idx');

        /*
        |--------------------------------------------------------------------------
        | payments
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('payments', ['company_id', 'created_at', 'id'], 'payments_company_created_idx');
        $this->safeIndex('payments', ['subscription_id', 'created_at'], 'payments_subscription_created_idx');
        $this->safeIndex('payments', ['status', 'created_at'], 'payments_status_created_idx');
        $this->safeIndex('payments', ['paid_at'], 'payments_paid_at_idx');

        /*
        |--------------------------------------------------------------------------
        | plans
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('plans', ['is_active', 'sort_order'], 'plans_active_sort_idx');

        /*
        |--------------------------------------------------------------------------
        | company_users
        |--------------------------------------------------------------------------
        */
        $this->safeIndex('company_users', ['user_id'], 'company_users_user_idx');
        $this->safeIndex('company_users', ['company_id', 'revoked_at'], 'company_users_company_revoked_idx');
    }

    public function down(): void
    {
        $indexes = [
            'contacts' => ['contacts_company_active_idx', 'contacts_company_name_idx'],
            'invoices' => [
                'invoices_company_created_idx',
                'invoices_company_contact_idx',
                'invoices_company_doctype_date_idx',
                'invoices_journal_entry_idx',
                'invoices_original_invoice_idx',
            ],
            'invoice_lines' => ['invoice_lines_invoice_sort_idx', 'invoice_lines_account_idx'],
            'expenses' => [
                'expenses_company_status_date_idx',
                'expenses_company_created_idx',
                'expenses_company_contact_idx',
                'expenses_source_document_idx',
                'expenses_journal_entry_idx',
                'expenses_account_idx',
            ],
            'expense_lines' => ['expense_lines_expense_sort_idx', 'expense_lines_account_idx'],
            'fiscal_periods' => ['fiscal_periods_company_status_idx'],
            'journal_entries' => [
                'journal_entries_company_status_date_idx',
                'journal_entries_company_created_idx',
                'journal_entries_source_idx',
                'journal_entries_period_idx',
            ],
            'journal_lines' => [
                'journal_lines_journal_entry_idx',
                'journal_lines_account_entry_idx',
                'journal_lines_contact_idx',
            ],
            'bank_transactions' => [
                'bank_tx_account_date_idx',
                'bank_tx_import_idx',
                'bank_tx_journal_entry_idx',
                'bank_tx_company_date_idx',
            ],
            'bank_statement_imports' => [
                'bank_imports_company_created_idx',
                'bank_imports_account_period_idx',
            ],
            'documents' => [
                'documents_company_created_idx',
                'documents_company_ocr_status_idx',
                'documents_company_doctype_idx',
                'documents_retention_idx',
            ],
            'ai_suggestions' => [
                'ai_suggestions_source_idx',
                'ai_suggestions_accepted_idx',
            ],
            'letterings' => [
                'letterings_company_matched_idx',
                'letterings_contact_matched_idx',
            ],
            'accounts' => [
                'accounts_company_class_active_idx',
                'accounts_company_lettrable_idx',
            ],
            'tax_rates' => ['tax_rates_company_active_idx'],
            'subscriptions' => [
                'subscriptions_company_created_idx',
                'subscriptions_status_period_end_idx',
                'subscriptions_plan_idx',
            ],
            'payments' => [
                'payments_company_created_idx',
                'payments_subscription_created_idx',
                'payments_status_created_idx',
                'payments_paid_at_idx',
            ],
            'plans' => ['plans_active_sort_idx'],
            'company_users' => [
                'company_users_user_idx',
                'company_users_company_revoked_idx',
            ],
        ];

        foreach ($indexes as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $names) {
                foreach ($names as $name) {
                    if (Schema::hasIndex($table, $name)) {
                        $t->dropIndex($name);
                    }
                }
            });
        }
    }

    /**
     * Idempotently create an index with an explicit name.
     *
     * Skips silently if the table is missing, if the index already exists,
     * or if the column list does not match the current schema.
     */
    private function safeIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumns($table, $columns)) {
            return;
        }

        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }
};
