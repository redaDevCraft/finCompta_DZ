# 14 - Accounting Glossary

This glossary is the canonical terminology reference for both users and developers.

## A

### Account

A bucket used to classify amounts (cash, supplier debt, revenue, expense, tax).  
Related: `settings/accounts`, ledger pages.

### Aged Balance

A report grouping open receivable/payable amounts by age buckets (for example 0-30, 31-60 days).

## B

### Balance (Trial Balance)

Summary by account of total debit, total credit, and net balance for a period.

### Bilan

Balance sheet report showing assets, liabilities, and equity snapshot at a given date.

### Bon de commande

Manual payment workflow artifact generated as a PDF purchase order for subscription payment outside hosted checkout.

## C

### Chart of Accounts

The full list of accounting accounts used by a company, often aligned with SCF classes.

### Credit

One side of accounting entries. Its economic meaning depends on account type.

## D

### Debit

One side of accounting entries. Its economic meaning depends on account type.

### Draft Entry

A journal entry not yet officially posted.

## E

### Entry Lock

Protection mechanism preventing editing/posting based on lock settings (date/password/period).

## F

### Fiscal Period

Accounting time segment (month/year) that can be opened, locked, or reopened.

## G

### G50

Algerian VAT declaration context. In this project, tax rate reporting codes support G50-oriented mapping and VAT reporting exports.

### General Ledger

Detailed account-by-account history of posted accounting lines.

## J

### Journal

Book/category where entries are recorded (sales, purchases, bank, etc.).

### Journal Entry

A grouped accounting operation containing multiple journal lines.

### Journal Line

A single debit or credit line inside a journal entry.

## L

### Lettering (Lettrage)

Matching open debit/credit lines on receivable/payable accounts to mark settlement progress.

## M

### Middleware

Request checkpoint executed before controller logic (for auth, tenant context, subscription state, permissions, throttling, etc.).

## P

### Posting

Action that validates an entry as official and immutable under normal operations.

## R

### Reconciliation

Process of matching bank transactions with accounting entries.

### Report Run

Asynchronous export task record (queued, processing, completed, failed).

### Scheduled Subscription Change

Deferred plan/cycle transition stored on subscription and applied at effective date.

## S

### SCF

Algerian accounting framework used to organize accounts and reporting logic.

### Subscription Grace Period

Temporary access period after payment issues before full feature lock.

### Subscription Middleware (`subscribed`)

Middleware gate that allows access only for trial/active/grace states and redirects to billing when access is no longer valid.

## T

### Trial Balance

Control report proving total debits and total credits remain equal.

### TVA (VAT)

Value-added tax tracked on sales and purchases; reported periodically.

### Two-step approval (manual payment)

Admin control requiring two different admins to approve high-value manual subscription payments.

## Cross-Links

- Beginner learning: `13-beginner-accounting-guide.md`
- Technical accounting engine: `07-accounting-engine-journals-ledger.md`
- Reports and VAT: `09-reports-vat-g50-exports.md`

