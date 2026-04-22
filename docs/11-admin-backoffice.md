# 11 - Admin Backoffice

## Purpose

Describe global admin scope, permissions model, and operational actions available under `/admin`.

## Access Model

- Entry gate: `spatie_role:admin`
- Feature gates: `spatie_permission:*`
- Admin scope is global (not tied to a single company context in the same way app routes are).

## Admin Modules

- Dashboard overview
- Payment confirmation/rejection
- Refund request review
- Plan and feature management
- Company listing/detail
- User admin role toggles
- Subscription lifecycle operations (cancel, reactivate, extend)

## Technical Notes

- Routes are grouped under `prefix('admin')->name('admin.')`.
- Controllers live in `app/Http/Controllers/Admin/*`.
- Actions that change financial access state are permission-protected.

## Risk-Sensitive Operations

- Payment confirmation affects subscription access.
- Plan changes affect feature gates and pricing behavior.
- Admin role toggling changes security boundaries.

## Beginner note

The admin area is for platform operations, not daily bookkeeping.

## Developer note

New admin actions should always:

1. Introduce explicit permission keys.
2. Log sensitive changes.
3. Return clear flash feedback.

## Related Files

- `routes/web.php` (admin group)
- `app/Http/Controllers/Admin/AdminDashboardController.php`
- `app/Http/Controllers/Admin/PaymentConfirmationController.php`
- `app/Http/Controllers/Admin/RefundRequestAdminController.php`
- `app/Http/Controllers/Admin/PlanController.php`
- `app/Http/Controllers/Admin/PlanFeatureController.php`
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `resources/js/Pages/Admin/*`

