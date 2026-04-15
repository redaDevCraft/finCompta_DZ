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

## License

This project is distributed under the MIT License.
