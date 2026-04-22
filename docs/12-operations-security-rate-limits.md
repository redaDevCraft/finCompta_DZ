# 12 - Operations, Security, and Rate Limits

## Purpose

Document production safeguards for confidentiality, integrity, availability, and tenant fairness, including the latest billing and scheduler protections.

## Security Controls

### Authentication and Authorization

- authenticated app features require `auth` and often `verified`.
- tenant routes require `company` context.
- accounting write operations use company-role gates.
- admin operations use global role + permission checks.

### Billing Integrity Controls

- Chargily webhooks require signature verification.
- duplicate event detection stored in webhook log table.
- webhook mode mismatch protection (test payload rejected in live mode).
- strict amount/currency matching before payment success transition.

### Data and File Controls

- proof files and generated PDFs are stored in private/local storage paths.
- proof uploads enforce mime, size, and digest capture (SHA-256) for traceability.

## Reliability and Consistency Controls

### Queue Controls

- heavy PDF/report generation is asynchronous.
- invoice PDF generation runs on dedicated queue (`pdf`).
- job dispatch uses `afterCommit()` where needed to avoid race-on-commit failures.

### Access Consistency Controls

- `EnsureSubscriptionActive` enforces access using trial/active/grace logic.
- due scheduled subscription changes are applied before access decision.

### Scheduler Controls

Scheduled commands include overlap protection and failure logging:

- report retention sweep,
- stuck report-run reaper,
- scheduled subscription-change applier.

This reduces silent drift for unattended operations.

## Rate Limiting Strategy

Implemented in `RateLimiterServiceProvider` with tenant-aware composite keys.

Key design properties:

- user-first keys with IP fallback for public routes,
- tenant included to avoid cross-tenant budget collisions,
- separate limiter namespaces per bucket,
- explicit rationale per bucket in code comments.

### Current Named Limiters

- `suggest`: typeahead endpoints (`/suggest/*`) - anti-scraping.
- `reports-poll`: export status polling - anti-loop.
- `reports-queue`: expensive export enqueueing - queue fairness.
- `reports-download`: artifact download - IO/bandwidth fairness.
- `billing-checkout`: checkout session spam guard.
- `billing-bon`: duplicate manual transfer intent guard.
- `trial-start`: public trial-funnel abuse guard.

## Logging and Monitoring Surfaces

- `payment_webhook_logs` captures signature validity, duplication, payload, event id.
- payment metadata stores failure reasons and approval traces.
- report run status rows expose async pipeline health.
- app/performance logs provide request-level operational observability.

## Operational Playbook Notes

- supervise queue workers in production (auto-restart on failure).
- verify scheduler heartbeat (`schedule:run`) in environment configuration.
- align cache backend with concurrency expectations (Redis recommended for shared limiter state at scale).
- keep backup, restore, and artifact-retention policy documented outside code.

## Beginner note

Rate limits and scheduler checks are "safety rails": they stop abuse and prevent background tasks from silently falling behind.

## Developer note

When introducing any new endpoint or background operation, explicitly evaluate:

1. authentication/authorization boundary,
2. tenant isolation behavior,
3. throttling needs,
4. queue vs synchronous execution,
5. logging/audit surface for troubleshooting.

## Related Files

- `bootstrap/app.php`
- `routes/web.php`
- `routes/console.php`
- `app/Providers/RateLimiterServiceProvider.php`
- `app/Http/Middleware/EnsureSubscriptionActive.php`
- `app/Http/Middleware/PerformanceRequestLogger.php`
- `app/Models/PaymentWebhookLog.php`
- `app/Services/SubscriptionService.php`
- `app/Services/Reports/ReportRunService.php`

