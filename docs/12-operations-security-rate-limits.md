# 12 - Operations, Security, and Rate Limits

## Purpose

Document operational safeguards that protect stability, security, and tenant fairness.

## Security Controls

- Auth + verification middleware for app features.
- Company scoping and role checks.
- Spatie role/permission for admin boundaries.
- CSRF protections (with explicit webhook exceptions).
- Payment webhook signature verification.

## Reliability Controls

- Queue-based report generation to prevent request timeouts.
- Subscription and feature gates to ensure predictable access states.
- Flash-based business error messaging for user guidance.

## Rate Limiting Strategy

Defined in `RateLimiterServiceProvider` and attached to selected routes:

- Suggest APIs (`throttle:suggest`)
- Billing checkout operations
- Report queue requests
- Report status polling
- Report downloads

These limits protect from accidental loops and abusive scraping while preserving normal UX.

## Logging and Monitoring Surfaces

- Payment webhook logs
- Report run statuses
- Application logs and performance request logger middleware

## Disaster and Recovery Basics

- Daily backup policy should be documented operationally.
- Export endpoints allow data portability.
- Queue workers must be supervised in production.

## Beginner note

Security controls keep accounting data private and reliable, even when many companies use the same platform.

## Developer note

Any new heavy endpoint should be evaluated for:

1. Queue suitability
2. Throttle policy
3. Audit logging
4. User-safe error handling

## Related Files

- `bootstrap/app.php`
- `app/Providers/RateLimiterServiceProvider.php`
- `app/Http/Middleware/PerformanceRequestLogger.php`
- `app/Models/PaymentWebhookLog.php`
- `app/Services/Reports/ReportRunService.php`
- `routes/web.php`

