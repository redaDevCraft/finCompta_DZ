# 03 - Routing and Middleware

## Purpose

Explain route groups, middleware boundaries, and why each gate exists.

## Route Topology

`routes/web.php` is organized into:

- Public routes (landing, legal, OAuth callback, webhooks)
- Authenticated routes (`auth`)
- Admin routes (`spatie_role:admin` + permissions)
- Billing routes (requires company, not active subscription)
- Core app routes (`auth`, `verified`, `company`, `subscribed`)

`routes/auth.php` handles Breeze auth lifecycle routes.

## Middleware Boundaries

Important aliases configured in `bootstrap/app.php`:

- `company` -> ensure company is selected
- `subscribed` -> enforce active trial/subscription/grace
- `role` -> company-level roles (`owner`, `accountant`)
- `spatie_role` / `spatie_permission` -> global admin security
- `plan_feature` -> plan-based feature access

## Why Billing Is Outside `subscribed`

Billing pages require company context but remain accessible without active subscription so users can recover access and pay.

## Flash and Shared Props

`HandleInertiaRequests` shares:

- `auth` user/roles/permissions
- `flash` (`success`, `warning`, `error`)
- `subscription` summary
- `allowed_features`

These props are consumed by front-end layout and notifications.

## Business Error Handling

Business exceptions (e.g., `422` with explicit user-facing message) are converted into UI-friendly responses through global exception handling in `bootstrap/app.php`.

## Developer note

When adding new feature routes:

1. Decide if feature is public, billing-level, or app-level.
2. Attach minimum required middleware.
3. Add role and plan guards deliberately.
4. Ensure front-end receives required shared props.

## Beginner note

Middleware is a checkpoint system: before opening a page, the app checks whether the user is allowed and whether required setup is complete.

## Related Files

- `routes/web.php`
- `routes/auth.php`
- `bootstrap/app.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Middleware/EnsureCompanySelected.php`
- `app/Http/Middleware/EnsureSubscriptionActive.php`

