# FinCompta DZ Documentation

This documentation is written in English and targets two audiences:

- Developers who need to understand architecture, code flows, and extension points.
- Accounting beginners who need plain-language explanations of concepts and app actions.

## How To Use This Documentation

- If you are a developer, start with `02-architecture.md` then `03-routing-and-middleware.md`.
- If you are new to accounting, start with `13-beginner-accounting-guide.md` and `14-accounting-glossary.md`.
- For any feature, read both the technical chapter and glossary terms to align business and code understanding.

## Table of Contents

1. [Product Overview](./01-product-overview.md)
2. [Architecture](./02-architecture.md)
3. [Routing and Middleware](./03-routing-and-middleware.md)
4. [Data Model and Migrations](./04-data-model-and-migrations.md)
5. [Auth, Onboarding, and Company Context](./05-auth-onboarding-company-context.md)
6. [Subscriptions, Billing, and Payments](./06-subscriptions-billing-payments.md)
7. [Accounting Engine: Journals and Ledger](./07-accounting-engine-journals-ledger.md)
8. [Reconciliation and Lettering](./08-reconciliation-and-lettering.md)
9. [Reports, VAT/G50, and Exports](./09-reports-vat-g50-exports.md)
10. [Frontend Inertia + React Guide](./10-frontend-inertia-react-guide.md)
11. [Admin Backoffice](./11-admin-backoffice.md)
12. [Operations, Security, and Rate Limits](./12-operations-security-rate-limits.md)
13. [Beginner Accounting Guide](./13-beginner-accounting-guide.md)
14. [Accounting Glossary](./14-accounting-glossary.md)
15. [Developer Onboarding Checklist](./15-developer-onboarding-checklist.md)

## Source of Truth Policy

- All behavior claims in this documentation map to code in:
  - `routes/web.php`, `routes/auth.php`
  - `bootstrap/app.php`
  - `app/Http/Controllers/*`
  - `app/Services/*`
  - `app/Models/*`
  - `resources/js/*`
- If implementation changes, update the relevant chapter and glossary entries in the same pull request.

## Documentation Conventions

- Every chapter uses this structure:
  - Purpose
  - Concepts
  - User Flow
  - Technical Flow
  - Edge Cases
  - Related Files
- Beginner callouts are marked as: **Beginner note**
- Developer implementation details are marked as: **Developer note**

