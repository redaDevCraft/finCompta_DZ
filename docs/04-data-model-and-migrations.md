# 04 - Data Model and Migrations

## Purpose

Provide a detailed schema-level map of entities and invariants, with emphasis on the new subscription scheduling and billing approval capabilities.

## Entity Domains

### Tenant and Identity

- `users`
- `companies`
- `company_users` (tenant membership + company-scoped role)

### Accounting Core

- `accounts`, `journals`, `journal_entries`, `journal_lines`
- `fiscal_periods`
- reconciliation/lettering entities

### Commercial and Sales

- `contacts`
- `invoices`, `invoice_lines`, `invoice_vat_buckets`, invoice payment records
- `expenses`, `expense_lines`

### SaaS Billing

- `plans`
- `subscriptions`
- `payments`
- `payment_webhook_logs`
- `refund_requests`

### Async Reporting

- `report_runs`
- artifact storage paths and report metadata

## Subscription Schema (Detailed)

`subscriptions` now models both current and deferred billing intent:

- Current state fields:
  - `plan_id`, `billing_cycle`, `status`,
  - `trial_ends_at`, `current_period_started_at`, `current_period_ends_at`,
  - `grace_ends_at`, `canceled_at`, `cancel_at`,
  - `last_payment_method`.
- Deferred-change fields (new):
  - `next_plan_id` (nullable FK -> `plans`),
  - `next_billing_cycle`,
  - `next_change_effective_at`,
  - `pending_change_reason`,
  - `pending_change_requested_at`.

These fields support "pay now, apply later" behaviors (downgrade and yearly->monthly cycle switch).

## Payment Schema Behavior (Operational)

`payments` captures gateway-level transaction state and manual-approval metadata:

- status lifecycle (for example: `pending`, `processing`, `paid`, `failed`);
- gateway/method fields (`chargily`, `bon_de_commande`, etc.);
- approval state fields for manual flow (`proof_missing`, `proof_uploaded`, `awaiting_second_approval`, `approved`, `rejected`);
- proof file metadata (path, mime, size, checksum, uploader);
- `meta` JSON for audit breadcrumbs (webhook snapshots, failure reasons, approval trail).

## Invoice Schema and Immutability Model

`Invoice` is tenant-scoped and immutable after issuance, except selected technical fields.

Key model behavior:

- global scope on `company_id` when `currentCompany` exists,
- UUID primary key generation,
- editing hard-blocked after issuance except `status`, `pdf_path`, `journal_entry_id`.

This protects legal/accounting integrity while still allowing post-issuance processing artifacts.

## New Migration: Scheduled Subscription Changes

Migration: `2026_04_22_150000_add_scheduled_changes_to_subscriptions.php`

Adds:

- deferred plan FK (`next_plan_id`),
- deferred cycle (`next_billing_cycle`),
- effective date (`next_change_effective_at`),
- semantic reason (`pending_change_reason`),
- request timestamp (`pending_change_requested_at`).

Rollback removes all these fields and the FK constraint.

## Service-Level Invariants Backed by Schema

- `SubscriptionService` computes change type (upgrade/downgrade/cycle/lateral) using plan price per cycle.
- Failed payments can move expired subscriptions to `past_due` and set `grace_ends_at`.
- `applyScheduledChanges()` only mutates due rows and clears pending fields atomically.
- `InvoiceService` computes VAT buckets from canonical line computation and requires matching active tax rates.

## Migration Strategy

- Keep additive and backward-safe migrations as default.
- For each schema change:
  - update model fillable/casts/relations,
  - update services enforcing invariants,
  - update route/controller validation and frontend forms,
  - update docs in same change set.

## Beginner note

The schema is not just storage; it encodes the business timeline (trial, paid, grace, scheduled changes) so the system can enforce access and accounting rules correctly over time.

## Related Files

- `database/migrations/*`
- `database/migrations/2026_04_22_150000_add_scheduled_changes_to_subscriptions.php`
- `app/Models/Subscription.php`
- `app/Models/Payment.php`
- `app/Models/Invoice.php`
- `app/Services/SubscriptionService.php`
- `app/Services/InvoiceService.php`

