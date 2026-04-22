# 08 - Reconciliation and Lettering

## Purpose

Document bank reconciliation and ledger lettering (lettrage), including matching logic and user workflows.

## Concepts

- Reconciliation: match bank transactions with accounting entries.
- Lettering (lettrage): match open debit/credit lines on receivable/payable accounts.
- Manual posting: create new entry when no match exists.
- Exclusion: mark transaction as intentionally ignored.

## Bank Reconciliation Flow

1. Import bank data.
2. Show unmatched transactions and candidate accounting lines.
3. Confirm match, exclude, or create manual post.
4. Update reconciliation status and downstream reports.

## Lettering Flow

1. Open lettrage page for receivable/payable accounts.
2. Select open lines by contact/reference/amount.
3. Match manually or run automatic matching heuristics.
4. Unmatch if needed and re-open lines.

## Technical Components

- `ReconciliationController` + `ReconciliationService`
- `LetteringController` + `LetteringService`
- Frontend pages:
  - `Pages/Bank/Reconcile.jsx`
  - `Pages/Ledger/Lettering.jsx`

## Edge Cases

- Amount/date mismatch with near matches.
- Multiple candidate lines for one transaction.
- Partial settlements requiring several matches.
- Reversals/unmatching after mistaken confirmations.

## Beginner note

Reconciliation answers: “Does my accounting cash movement match what the bank actually recorded?”

## Developer note

Keep matching logic deterministic and auditable; ambiguous cases should prefer user confirmation over silent auto-match.

## Related Files

- `app/Http/Controllers/ReconciliationController.php`
- `app/Services/ReconciliationService.php`
- `app/Http/Controllers/LetteringController.php`
- `app/Services/LetteringService.php`
- `resources/js/Pages/Bank/Reconcile.jsx`
- `resources/js/Pages/Ledger/Lettering.jsx`

