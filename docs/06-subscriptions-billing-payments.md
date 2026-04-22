# 06 - Subscriptions, Billing, and Payments

## Purpose

Explain, end-to-end, how trialing, checkout, manual transfer, webhook verification, admin confirmation, and subscription state transitions work in the current architecture.

## Core Concepts

- `Plan`: defines pricing and enabled features.
- `Subscription`: tenant access state and billing timeline.
- `Payment`: a concrete payment intent/attempt for one company-plan-cycle tuple.
- `Approval status`: manual verification state used mainly for Bon de commande workflow.
- `Scheduled change`: deferred plan/cycle switch to apply later at period boundary.

## Subscription State Model

Main states:

- `trialing`: temporary trial access.
- `active`: paid and currently valid.
- `past_due`: period ended with failed/absent payment, grace may apply.
- `canceled`: terminated access.

Access rule in middleware:

- allow if active trial,
- allow if active period,
- allow if in grace window,
- otherwise redirect to billing.

## Payment Channels

### 1) Chargily Hosted Checkout

Flow:

1. User selects plan/cycle in billing checkout page.
2. `BillingController@startChargily` validates input and blocks conflicting in-flight payment.
3. Payment row created with `pending` state.
4. Hosted checkout created through `ChargilyService`.
5. Payment updated to `processing` with checkout id/url.
6. User redirected to hosted page.
7. Webhook confirms paid/failed result and calls subscription transitions.

### 2) Bon de commande (Manual Transfer)

Flow:

1. User starts Bon flow from billing page.
2. Payment row created with:
   - `gateway=bon_de_commande`,
   - `status=pending`,
   - `approval_status=proof_missing`.
3. PDF order form generated and stored.
4. User downloads PDF and performs bank transfer externally.
5. User uploads transfer proof (`pdf/jpg/png`, max 10 MB).
6. Payment becomes `processing` + `approval_status=proof_uploaded`.
7. Admin validates or rejects.

## Webhook Trust and Idempotency

`BillingController@webhookChargily` performs strict checks before state mutation:

- signature verification,
- payment lookup by metadata/checkpoint id,
- duplicate event detection via `payment_webhook_logs`,
- mode safety check (live/test mismatch guard),
- amount and currency consistency check.

Only after all checks does it call:

- `SubscriptionService::markPaymentSucceeded()`, or
- `SubscriptionService::markPaymentFailed()`.

## Scheduled Change Logic

For downgrade/certain cycle changes, the payment may not immediately switch active plan/cycle.

Instead:

- current plan remains until period boundary,
- `next_plan_id` / `next_billing_cycle` / `next_change_effective_at` are set,
- pending reason/timestamp are stored,
- change is applied when due by middleware and scheduler command.

This avoids accidental mid-period entitlement shrink.

## Admin Payment Operations

`PaymentConfirmationController` enables:

- list pending and processing payments,
- confirm supported gateways,
- reject with optional reason,
- download manual proof files.

High-value Bon payments can require dual approval:

- first admin marks `awaiting_second_approval`,
- second distinct admin must perform final confirmation.

## Billing UI Components

- `Billing/Index.jsx`
  - subscription card with period/grace/scheduled-change indicators,
  - plan selector and checkout entrypoints,
  - payment history with status/action badges,
  - refund request creation and history.
- `Billing/BonDeCommande.jsx`
  - transfer beneficiary/bank details,
  - order form download,
  - proof upload and status feedback.

## Operational Guardrails

- one active pending/processing payment per subscription context.
- yearly->monthly immediate downgrade blocked.
- proof upload blocked once payment is finalized.
- manual proof includes stored SHA-256 and metadata trace.
- billing endpoints throttled (`billing-checkout`, `billing-bon`).

## Edge Cases

- User reaches success page before webhook -> payment may still be `processing`.
- Duplicate webhook event -> ignored after logging.
- Invalid webhook signature -> rejected (`403`).
- Gateway amount mismatch -> rejected (`422`).
- Expired subscription -> app blocked, billing still accessible.

## Beginner note

Billing determines who can use the app and for how long; it does not itself create accounting journal entries for customer bookkeeping operations.

## Developer note

Do not grant access from front-end return URL alone. The source of truth for paid state is signed provider callback or explicit admin confirmation path.

## Related Files

- `routes/web.php`
- `app/Http/Controllers/BillingController.php`
- `app/Http/Controllers/Admin/PaymentConfirmationController.php`
- `app/Http/Middleware/EnsureSubscriptionActive.php`
- `app/Services/SubscriptionService.php`
- `app/Models/Subscription.php`
- `app/Models/Payment.php`
- `app/Models/PaymentWebhookLog.php`
- `resources/js/Pages/Billing/Index.jsx`
- `resources/js/Pages/Billing/BonDeCommande.jsx`
- `resources/js/Pages/Admin/Payments/Index.jsx`

