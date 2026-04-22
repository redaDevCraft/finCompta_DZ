# 10 - Frontend Inertia + React Guide

## Purpose

Provide a practical implementation map of frontend architecture, data flow, global UX infrastructure, and integration boundaries with Laravel.

## Bootstrapping

- `resources/js/app.jsx` initializes Inertia app.
- The blade root template is `resources/views/app.blade.php`.
- Shared flash/auth/subscription props come from `HandleInertiaRequests`.
- `NotificationProvider` wraps app root for global flash/errors/confirm/prompt UX.

## Layout Strategy

- `Layouts/AuthenticatedLayout.jsx`: main app shell, sidebar, alerts.
- `Layouts/AdminLayout.jsx`: admin shell.
- `Layouts/GuestLayout.jsx`: public/auth views.

## Page Organization

- `Pages/Landing/*` for public marketing pages.
- `Pages/Auth/*` for login/register/reset flows.
- `Pages/Invoices/*`, `Pages/Expenses/*`, `Pages/Bank/*`, `Pages/Ledger/*`, `Pages/Reports/*`, `Pages/Settings/*`.
- `Pages/Admin/*` for admin backoffice.
- `Pages/Billing/*` for plan selection, checkout flows, and manual transfer handling.

## Data Flow Pattern

1. Laravel controller returns `Inertia::render(PageName, props)`.
2. Frontend page receives props directly.
3. Actions use Inertia router calls (`get`, `post`, etc.).
4. Flash notifications are rendered via `NotificationProvider`.

## Global UI Infrastructure

### Notification Context

- receives initial flash/errors from initial Inertia page props,
- listens to Inertia success events and raises normalized notifications,
- supports modal confirmation and prompt interactions used by admin/payment actions.

### Shared Security and Feature UX

- pages rely on backend middleware for hard security,
- frontend uses shared props for conditional rendering and user guidance only.

## UX/State Patterns

- URL-driven filters for reports and list pages.
- Async status polling for report runs.
- Permission/feature gating via shared props and route middleware.
- Inline status and badge-driven workflows for billing/subscription/payment states.

## Beginner note

Frontend pages only display and submit data; accounting correctness is enforced mainly in backend services.

## Developer note

When adding a page:

1. Add route and controller return.
2. Create page component under `Pages/*`.
3. Add shared props dependencies if needed.
4. Keep filter state URL-backed for reload/share consistency.
5. Reuse notification context for consistent confirmation/error UX.

## Related Files

- `resources/js/app.jsx`
- `resources/views/app.blade.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Pages/Billing/Index.jsx`
- `resources/js/Pages/Admin/Payments/Index.jsx`
- `resources/js/Context/NotificationContext.jsx`

