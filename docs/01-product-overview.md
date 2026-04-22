# 01 - Product Overview

## Purpose

Explain what FinCompta DZ does, who it serves, and which business problems it solves.

## What FinCompta DZ Is

FinCompta DZ is a Laravel + Inertia (React) cloud accounting SaaS for Algerian businesses and accounting firms.

It centralizes:

- Sales invoicing
- Purchase/expense capture
- Journal posting and ledger views
- Bank reconciliation
- VAT reporting (including G50-oriented tax mapping)
- Subscription billing and admin operations

## Target Users

- SME owners (single or multiple companies)
- In-house accountants
- External accounting firms (multi-client operations)
- Platform administrators (plans, subscriptions, payment reviews)

## Main Functional Pillars

- Accounting core: chart of accounts, journals, entries, ledger, trial balance.
- Commercial flows: invoices, expenses, contacts.
- Banking: statement import and reconciliation.
- Reporting: VAT, bilan, aged balances, analytic reports, export jobs.
- SaaS layer: trials, subscriptions, Chargily integration, manual purchase order workflow.

## Beginner note

Think of FinCompta DZ as one place where business events (selling, buying, paying, receiving) are converted into accounting records and reports.

## Developer note

The app is multi-tenant by `company_id` and relies on middleware to ensure the current company context before entering business routes.

## Typical User Journey

1. User signs in (classic auth or Google OAuth).
2. User creates/selects a company.
3. Trial starts and billing becomes available.
4. User records invoices/expenses/bank operations.
5. System generates entries and reports.
6. Admin/owner controls subscription state and access.

## Related Files

- `README.md`
- `routes/web.php`
- `app/Http/Controllers/LandingController.php`
- `resources/js/Pages/Landing/Home.jsx`

