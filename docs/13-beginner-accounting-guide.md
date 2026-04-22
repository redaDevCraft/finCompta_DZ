# 13 - Beginner Accounting Guide

## Purpose

Teach accounting basics in plain language and connect each concept to concrete actions in FinCompta DZ.

## 1) What Accounting Does

Accounting records every business event that affects money, assets, debts, income, or costs.

In FinCompta DZ, this happens through:

- Invoices (sales)
- Expenses (purchases/costs)
- Bank transactions
- Journal entries

## 2) Core Concepts (Simple)

- Account: a category where amounts are stored (cash, supplier debt, revenue, etc.).
- Debit/Credit: two sides of each accounting movement.
- Journal entry: one operation with at least two lines.
- Balanced entry: total debits = total credits.
- Ledger: full history for one account.
- Trial balance: summary by account to verify global balance.
- VAT: tax collected/supported and reported periodically.

For formal definitions, see `14-accounting-glossary.md`.

## 3) What Happens When You Click Key Actions

### Confirm Expense

- The app checks if expense is still editable.
- It creates draft journal lines (expense account, VAT deductible, supplier payable).
- It verifies balance.
- If unbalanced, you get a clear business error.

### Post Entry

- The app checks if period is open and entry is postable.
- Status changes from draft to posted.
- Entry becomes part of official ledgers and reports.

### Reconcile Bank Line

- The app tries to match a bank transaction with accounting lines.
- You can confirm match, exclude, or create manual posting.
- Reconciliation status updates cash visibility.

### Generate VAT Report

- App aggregates invoice/expense VAT values by tax setup.
- Report shows deductible vs collected amounts.
- Export can run asynchronously for large datasets.

## 4) Mini Practical Example

Scenario: You buy office supplies for 11,900 DZD (10,000 HT + 1,900 VAT).

Typical accounting effect:

- Debit expense account: 10,000
- Debit VAT deductible account: 1,900
- Credit supplier payable account: 11,900

Why this matters:

- Expense increases cost.
- VAT deductible can reduce tax payable.
- Supplier payable shows debt until payment.

## 5) Common Beginner Questions

### Why do I see both debit and credit?

Because every transaction has two accounting effects. This keeps books consistent.

### Why does app block some edits?

Posted entries and locked periods protect accounting integrity and auditability.

### Why can totals look right but still error?

Line-level account mapping or period rules may still fail even if arithmetic seems correct.

## 6) Learning Path

1. Start with contacts, invoices, and expenses.
2. Review generated journal entries.
3. Understand account ledger for one account.
4. Use trial balance to validate global consistency.
5. Use VAT report and exports for reporting cycles.

## Related Chapters

- `07-accounting-engine-journals-ledger.md`
- `08-reconciliation-and-lettering.md`
- `09-reports-vat-g50-exports.md`
- `14-accounting-glossary.md`

