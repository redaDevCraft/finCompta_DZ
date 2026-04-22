# 04 - Data Model and Migrations

## Purpose

Document key entities and how schema design supports accounting + SaaS workflows.

## Core Domain Entities

### Tenant and Identity

- `users`
- `companies`
- `company_users` (membership and company-level role)

### Accounting Core

- `accounts` (chart of accounts)
- `fiscal_periods`
- `journals`
- `journal_entries`
- `journal_lines`
- lettering and reconciliation-related entities

### Commercial Operations

- `contacts`
- `invoices`, `invoice_lines`, VAT buckets
- `expenses`, `expense_lines`
- `documents`, OCR/suggestion entities

### Billing and SaaS

- `plans`
- `subscriptions`
- `payments`
- `payment_webhook_logs`
- `refund_requests`

### Reporting and Background Jobs

- `report_runs` and output artifacts

## Accounting Consistency Rules (Model + Service Level)

- Journal entry balance is validated before posting.
- Journal lines enforce debit/credit constraints.
- Fiscal period lock state blocks posting in closed periods.
- Posting state transitions are controlled by services.

## Migration Strategy

- Migrations are grouped by domain over time.
- Seeders initialize SCF chart and tax rates.
- Plan seeders initialize SaaS catalog.

## Developer note

When changing schema:

1. Add migration with backward-safe defaults.
2. Update model casts/relations.
3. Update services that enforce invariants.
4. Update UI filters/forms and docs page references.

## Beginner note

A data model is the app's memory structure. Each accounting action writes into specific tables so reports can be computed correctly later.

## Related Files

- `database/migrations/*`
- `database/seeders/ScfAccountsSeeder.php`
- `database/seeders/TaxRateSeeder.php`
- `app/Models/JournalEntry.php`
- `app/Models/JournalLine.php`
- `app/Models/Subscription.php`
- `app/Models/Payment.php`

