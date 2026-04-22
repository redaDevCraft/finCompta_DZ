# 10 - Frontend Inertia + React Guide

## Purpose

Provide a practical map of how frontend pages are structured and fed by backend data.

## Bootstrapping

- `resources/js/app.jsx` initializes Inertia app.
- The blade root template is `resources/views/app.blade.php`.
- Shared flash/auth/subscription props come from `HandleInertiaRequests`.

## Layout Strategy

- `Layouts/AuthenticatedLayout.jsx`: main app shell, sidebar, alerts.
- `Layouts/AdminLayout.jsx`: admin shell.
- `Layouts/GuestLayout.jsx`: public/auth views.

## Page Organization

- `Pages/Landing/*` for public marketing pages.
- `Pages/Auth/*` for login/register/reset flows.
- `Pages/Invoices/*`, `Pages/Expenses/*`, `Pages/Bank/*`, `Pages/Ledger/*`, `Pages/Reports/*`, `Pages/Settings/*`.
- `Pages/Admin/*` for admin backoffice.

## Data Flow Pattern

1. Laravel controller returns `Inertia::render(PageName, props)`.
2. Frontend page receives props directly.
3. Actions use Inertia router calls (`get`, `post`, etc.).
4. Flash notifications are rendered via `NotificationProvider`.

## UX/State Patterns

- URL-driven filters for reports and list pages.
- Async status polling for report runs.
- Permission/feature gating via shared props and route middleware.

## Beginner note

Frontend pages only display and submit data; accounting correctness is enforced mainly in backend services.

## Developer note

When adding a page:

1. Add route and controller return.
2. Create page component under `Pages/*`.
3. Add shared props dependencies if needed.
4. Keep filter state URL-backed for reload/share consistency.

## Related Files

- `resources/js/app.jsx`
- `resources/views/app.blade.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Context/NotificationContext.jsx`

