# FinCompta DZ

FinCompta DZ is a Laravel + React accounting platform tailored for Algerian businesses.  
It centralizes invoicing, expenses, bank reconciliation, ledger posting, VAT reporting, and company-level compliance workflows.

## Core Features

- Multi-company access with role-based permissions (`owner`, `accountant`)
- Invoice lifecycle: draft, issue, void, credit note, PDF export
- Expense capture and confirmation workflow
- Bank import and reconciliation (match, exclude, manual posting)
- Journal and trial balance views
- VAT and income reporting with export support
- Contact and company settings management
- Document upload and suggestion application flow

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3
- **Frontend:** Inertia.js + React + Vite + Tailwind CSS
- **Auth/Security:** Laravel Breeze, Sanctum, Spatie Permission
- **Data/Exports:** Laravel Excel, League CSV, DOMPDF

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- A SQL database (MySQL/MariaDB/PostgreSQL/SQLite)

## Quick Start

```bash
git clone https://github.com/redaDevCraft/finCompta_DZ.git
cd finCompta_DZ
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Demo Credentials (Local Seeder)

When running in local environment with seeded data:

- **Email:** `demo@fincompta.dz`
- **Password:** `password`

## Useful Commands

```bash
# Run backend + queue + logs + Vite in one command
composer run dev

# Build production assets
npm run build

# Run tests
composer test
```

## Project Structure

- `app/Http/Controllers` - Request handling and route actions
- `app/Services` - Domain/business services (invoicing, tax, compliance, etc.)
- `resources/js/Pages` - Inertia React pages
- `resources/js/Components` - Reusable UI components
- `database/seeders` - Seeders for tax rates and demo data

## SaaS — Google OAuth, Chargily & Trial

FinCompta DZ ships as a **multi-tenant SaaS** with a public landing page, 3-day
free trial, Google sign-in, and Algerian payments (Edahabia / CIB via Chargily)
**and** a manual *bon de commande* flow for bank transfers.

### 1. Environment variables

Everything relevant is pre-wired in `.env.example`. Fill at minimum:

```dotenv
APP_URL=http://127.0.0.1:8000

# Google OAuth (Socialite) — omit GOOGLE_REDIRECT_URI locally (uses /auth/google/callback from current URL)
GOOGLE_CLIENT_ID=xxxxxxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxxxxxxxxxx

# Chargily Pay V2 (keep CHARGILY_MODE=test until you go live)
CHARGILY_MODE=test
CHARGILY_API_KEY=test_pk_xxx
CHARGILY_SECRET_KEY=test_sk_xxx
CHARGILY_WEBHOOK_SECRET=whsec_xxx

# SaaS plan / trial policy
SAAS_TRIAL_DAYS=3
SAAS_GRACE_DAYS=3
SAAS_PAYEE_NAME="Votre raison sociale"
SAAS_PAYEE_RIB="0000 0000 0000 0000 0000 00"
SAAS_PAYEE_BANK="BNA / BEA / CPA ..."
SAAS_ADMIN_EMAIL=admin@fincompta.dz
```

### 2. Google OAuth setup

1. Google Cloud Console → **APIs & Services → Credentials** → *Create OAuth
   client ID* (type *Web application*).
2. **Authorized redirect URIs:** must match the callback **exactly** (scheme, host, port). For local, add e.g. `http://127.0.0.1:8000/auth/google/callback` and, if you ever open the app as `http://localhost:8000`, that URI too. Production: `https://your-domain.tld/auth/google/callback`.
3. Copy Client ID / Secret into `.env`.

The login & register pages expose a **Continuer avec Google** button. On first
login the user is redirected to `/onboarding/company` to create their
company + start a free trial.

### 3. Chargily Pay V2 setup (test mode)

1. Create an account at <https://pay.chargily.com> in *Test mode*.
2. Grab `API Key`, `Secret Key`, and the `Webhook secret`.
3. Add the webhook endpoint in your Chargily dashboard:
   `https://your-domain.tld/webhooks/chargily`
4. The route is already CSRF-exempted and HMAC-SHA256 verified inside
   `BillingController@webhookChargily`.

### 4. Bon de commande (manual bank transfer)

Selecting **Bon de commande** on the checkout page generates a **PDF**
(`resources/views/pdf/bon_de_commande.blade.php`) with the client company's
details and your RIB. The customer pays by bank transfer and uploads a proof;
the admin confirms the payment manually in `/billing` → *Bons de commande*.

### 5. Trial / subscription enforcement

- `EnsureSubscriptionActive` (`subscribed` middleware alias) wraps all app
  routes (`/dashboard`, `/invoices`, `/expenses`, `/ledger`, `/reports`, …).
- Users in **trial** and **grace** (`SAAS_GRACE_DAYS`) keep full access; past
  grace they're sent to `/billing` with a warning.
- A banner in `AuthenticatedLayout` warns the user 3 days before the trial
  ends and after any `past_due`.

### 6. Seeders

```bash
php artisan migrate:fresh --seed
```

will create:

- SCF chart of accounts
- Tax rates
- **Plan catalog** (`Starter`, `Pro`, `Enterprise`) via `PlanSeeder`
- Demo company + owner (local only)

### 7. Going live

1. `CHARGILY_MODE=live` and swap API keys for the live ones.
2. Update Google OAuth authorized origins/redirects for production domain.
3. Verify `APP_URL` and `SAAS_PAYEE_*` are production values.

## License

This project is distributed under the MIT License.
