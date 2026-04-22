# 03 - Routing and Middleware

## Purpose

Describe the live route topology, middleware gates, and endpoint protection strategy after the latest billing/admin addons.

## Route Topology

`routes/web.php` is intentionally segmented by access level and tenant dependency:

1. Public routes:
   - Landing/pricing/legal pages.
   - OAuth redirect/callback routes.
   - Chargily webhook ingress routes.
2. Authenticated routes (`auth`):
   - Admin backoffice (`/admin/*`) via global role/permission gates.
   - Onboarding/company switcher.
   - Billing routes requiring `company` only.
   - Profile routes.
3. Core app routes (`auth`, `verified`, `company`, `subscribed`):
   - Accounting, reporting, settings, and core business operations.

`routes/auth.php` retains Breeze auth lifecycle.

## Middleware Layering Model

### Common Gate Aliases

- `company`: enforce selected tenant context.
- `subscribed`: enforce trial/active/grace access (`EnsureSubscriptionActive`).
- `role`: company-level RBAC for accounting actions (`owner`, `accountant`, etc.).
- `spatie_role` / `spatie_permission`: global admin RBAC.
- `plan_feature`: plan-gated product features.
- `throttle:*`: endpoint-specific anti-abuse policies.

### Billing and Recovery Boundary

Billing routes are under `auth + company`, not `subscribed`, so expired tenants can still:

- choose a plan,
- initiate payment,
- upload transfer proof,
- and recover access.

This is an architectural recovery guarantee, not an accidental bypass.

## Detailed Route Zones

### Public Webhook Zone

- `/webhooks/chargily` and `/chargilypay/webhook` are public POST endpoints.
- Signature validation and idempotency happen in controller logic, not in middleware.
- CSRF exemption is explicitly handled at bootstrap level for webhook compatibility.

### Admin Zone (`/admin`)

- Entry gate: `spatie_role:admin`.
- Fine-grained permission gates per module/action.
- New payment triage endpoints:
  - list pending/manual payments,
  - confirm (including optional two-step approval path),
  - reject with reason,
  - download proof artifact.

### Billing Zone (`/billing`)

- User-facing billing commands:
  - plan checkout view,
  - Chargily checkout creation and redirect hop,
  - success/failure return pages,
  - Bon de commande generation/download/proof upload,
  - refund request submission.
- Checkout and Bon generation are throttle-protected.

### Subscribed App Zone

- All accounting and financial operations remain behind subscription enforcement.
- `EnsureSubscriptionActive` now applies due scheduled subscription changes before access decision.

## Throttling by Endpoint Type

Applied via named limiters:

- `trial-start`: public trial funnel abuse control.
- `suggest`: typeahead scraping control.
- `reports-queue` / `reports-poll` / `reports-download`: queue and bandwidth fairness.
- `billing-checkout` / `billing-bon`: payment intent flood control.

## Inertia Shared-Prop Boundary

`HandleInertiaRequests` shares props consumed across route zones:

- auth context,
- flash/errors,
- subscription summary,
- allowed feature flags.

`NotificationProvider` uses these props globally to render modal notifications and confirmations.

## Developer note

For each new route, decide all four axes up front:

1. Access class: public / auth / company / subscribed / admin.
2. Authorization class: role, permission, feature flag.
3. Abuse class: needs throttle or not.
4. UX class: needs global flash/error behavior or custom page-level handling.

## Beginner note

Routes define "where users can go", middleware defines "who can pass", and service logic defines "what actually happens" once access is granted.

## Related Files

- `routes/web.php`
- `routes/auth.php`
- `bootstrap/app.php`
- `app/Http/Middleware/EnsureCompanySelected.php`
- `app/Http/Middleware/EnsureSubscriptionActive.php`
- `app/Providers/RateLimiterServiceProvider.php`
- `app/Http/Middleware/HandleInertiaRequests.php`

