# 01 - Product Overview

## Purpose

Explain what FinCompta DZ delivers, who it serves, and how product modules map to the current technical architecture.

## What FinCompta DZ Is

FinCompta DZ is a Laravel + Inertia (React) cloud accounting SaaS for Algerian businesses and accounting firms.

It centralizes:

- Sales invoicing
- Purchase/expense capture
- Journal posting and ledger views
- Bank reconciliation
- VAT reporting (including G50-oriented tax mapping)
- Subscription billing and admin operations
- Multi-tenant access and company context isolation

## Target Users

- SME owners (single or multiple companies)
- In-house accountants
- External accounting firms (multi-client operations)
- Platform administrators (plans, subscriptions, payment reviews)

## Main Functional Pillars

### 1) Accounting Core

- chart of accounts and journals,
- draft/post lifecycle for entries,
- ledger and trial-balance visibility,
- lock mechanisms by period/date/state.

### 2) Commercial Operations

- invoices and credit notes,
- expense capture and confirmation,
- contact management (clients/suppliers),
- VAT-aware line and total computations.

### 3) Treasury and Control

- bank import and reconciliation,
- lettering (open receivable/payable matching),
- exception handling for ambiguous matches.

### 4) Reporting and Exports

- VAT/G50-oriented reporting outputs,
- bilan and aging reports,
- async export pipeline with run tracking and artifact downloads.

### 5) SaaS Access Layer

- trial start and activation path,
- subscription states (trial/active/past_due/canceled),
- Chargily hosted checkout + webhook verification,
- Bon de commande + manual proof + admin confirmation,
- scheduled plan/cycle changes at period boundary.

## Product-to-Architecture Mapping

- User and tenant entrypoint: auth + onboarding + company selection.
- Access safety: middleware gates (`company`, `subscribed`, roles, permissions, plan features).
- Business rules: service layer (`InvoiceService`, `SubscriptionService`, `JournalService`, etc.).
- UI contract: Inertia responses + React pages.
- Heavy tasks: queue jobs and scheduler commands.

## End-to-End User Journey (Current)

1. User signs in (email/password or Google OAuth).
2. User creates/selects company context.
3. Trial starts; billing remains available for recovery.
4. User records business operations (sales, purchases, bank actions).
5. System enforces accounting invariants and updates reports.
6. User/admin handles subscription continuity through payment channels.
7. Async artifacts (reports, invoice PDFs) become downloadable when jobs complete.

## Beginner note

Think of FinCompta DZ as one place where business events (selling, buying, paying, receiving) are converted into accounting records and reports.

## Developer note

The app is multi-tenant by `company_id` and relies on middleware to ensure the current company context before entering business routes.

## Related Files

- `README.md`
- `routes/web.php`
- `routes/console.php`
- `app/Http/Controllers/LandingController.php`
- `app/Services/SubscriptionService.php`
- `app/Services/InvoiceService.php`
- `resources/js/Pages/Landing/Home.jsx`

