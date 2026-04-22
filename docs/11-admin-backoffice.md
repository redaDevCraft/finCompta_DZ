# 11 - Admin Backoffice

## Purpose

Describe global admin architecture, permission gates, and high-risk operational workflows, especially manual payment validation and subscription-impacting operations.

## Access Model

- Base gate: `spatie_role:admin`.
- Action gates: `spatie_permission:*` per module and mutation.
- Scope: global platform operations (not tenant-bound like normal company user routes).

## Admin Route Structure

All admin routes are grouped under `prefix('admin')->name('admin.')` with layered permissions.

Primary modules:

- dashboard,
- payment triage and confirmation,
- refund request review,
- plan and feature management,
- company/user management,
- subscription lifecycle operations.

## Payment Triage Module (Detailed)

### Listing and Filtering

- Shows latest pending/processing payment records with company + plan context.
- Includes gateway, method, status, approval status, amount.

### Manual Confirmation Flow

Controller: `PaymentConfirmationController@confirm`

Validation gates include:

- supported gateway check (`bon_de_commande` or `chargily`),
- mutable status check (`pending`/`processing` only),
- linked plan/company consistency,
- mandatory proof for Bon de commande.

### Double-Approval Mechanism

For high-value manual payments (threshold from config):

1. first admin action sets `approval_status=awaiting_second_approval` and stores approver metadata;
2. second confirmation must come from another admin;
3. only then payment is marked succeeded and subscription extended/activated.

### Reject Flow

- Optional reason captured.
- Payment marked failed via `SubscriptionService`.
- Audit metadata (`admin_rejected_by`, `admin_rejected_at`) persisted.

### Proof Artifact Access

- Admin can download transfer proof for Bon payments when present.
- File access is route-protected and streamed from private storage.

## Refund and Subscription Operations

- Refund requests have dedicated admin list/update workflow.
- Subscription admin actions support cancel/reactivate/extend flows and are permission-separated from read access.

## UI Layer for Admin Operations

`resources/js/Pages/Admin/Payments/Index.jsx` includes:

- table-based triage view,
- modal confirmations for confirm/reject actions via notification context,
- optional reject reason draft reused for next reject command.

Global notifications/confirmations come from `NotificationProvider`, ensuring consistent admin UX for risky actions.

## Risk-Sensitive Areas

- Payment confirmation immediately changes tenant access.
- Plan feature edits affect all downstream entitlement checks.
- Admin role toggles alter security boundaries platform-wide.

## Developer note

Any new admin mutation should include:

1. explicit permission key,
2. precondition checks for state transitions,
3. durable actor/time metadata for auditability,
4. clear user feedback (success/error flash),
5. test coverage for negative paths.

## Beginner note

Admin pages are platform-control tools: they manage who can access the product and under what commercial conditions, not bookkeeping entries for a company.

## Related Files

- `routes/web.php`
- `app/Http/Controllers/Admin/AdminDashboardController.php`
- `app/Http/Controllers/Admin/PaymentConfirmationController.php`
- `app/Http/Controllers/Admin/RefundRequestAdminController.php`
- `app/Http/Controllers/Admin/PlanController.php`
- `app/Http/Controllers/Admin/PlanFeatureController.php`
- `app/Http/Controllers/Admin/SubscriptionController.php`
- `resources/js/Pages/Admin/Payments/Index.jsx`
- `resources/js/Context/NotificationContext.jsx`

