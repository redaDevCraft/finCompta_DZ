# 15 - Developer Onboarding Checklist

## Purpose

Help new developers become productive quickly while preserving accounting correctness and SaaS safety.

## Week 1 Checklist

## 1. Local Setup

- [ ] Follow root `README.md` quick start.
- [ ] Run migrations + seeders.
- [ ] Validate login, onboarding, and dashboard access.
- [ ] Start queue worker and test report export lifecycle.
- [ ] Run scheduler locally and verify scheduled commands are registered.

## 2. Architecture Familiarization

- [ ] Read `02-architecture.md`
- [ ] Read `03-routing-and-middleware.md`
- [ ] Read `10-frontend-inertia-react-guide.md`
- [ ] Read `12-operations-security-rate-limits.md`

## 3. Core Accounting Understanding

- [ ] Read `13-beginner-accounting-guide.md` (yes, even as developer).
- [ ] Read `07-accounting-engine-journals-ledger.md`
- [ ] Trace one complete flow: expense confirmation -> journal entry -> ledger.
- [ ] Trace one complete flow: invoice issue -> journal draft -> PDF background generation.

## 4. Billing and Access Gates

- [ ] Read `06-subscriptions-billing-payments.md`
- [ ] Verify middleware behavior for:
  - no company
  - no active subscription
  - role mismatch
  - plan feature disabled
- [ ] Verify manual payment path:
  - Bon PDF generation
  - proof upload
  - admin confirm/reject
  - double-approval threshold behavior

## 5. First Safe Contribution Rules

- [ ] Preserve accounting invariants (balance, posting state, period locks).
- [ ] Keep business rules in services/models.
- [ ] Return user-friendly business errors for predictable failures.
- [ ] Add/update docs chapter when behavior changes.
- [ ] Preserve tenant isolation (`company_id` scoping) in every new query path.

## Change Checklist for New Features

- [ ] Route + middleware guards added.
- [ ] Controller/service/model responsibilities clear.
- [ ] Frontend page integrated with shared props and flash feedback.
- [ ] If heavy operation: queue + throttling considered.
- [ ] If lifecycle mutation: audit metadata (actor/time/reason) considered.
- [ ] Documentation updates in `docs/` completed.

## Suggested First Reading by File

- `routes/web.php`
- `bootstrap/app.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Services/JournalService.php`
- `app/Services/SubscriptionService.php`
- `app/Services/InvoiceService.php`
- `app/Providers/RateLimiterServiceProvider.php`
- `resources/js/app.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx`

