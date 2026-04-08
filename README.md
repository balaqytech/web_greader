# Web GReader

Web GReader is a Laravel application for managing student program registrations. It provides a public registration flow for families, an API for registration-related data, and a Filament-based admin panel for staff to manage enrollments, contracts, finance, and supporting records.

## What The Project Does

The application supports the full lifecycle of a student joining a program:

- publish available programs by branch
- collect student and family registration details
- create parent and student records
- create program enrollments for the current academic year
- capture and track contract signing
- manage enrollment approval and status transitions
- generate invoices and installments
- record payments and discounts
- manage users, roles, branches, add-ons, and academic years

## Main Areas

### Public Web Flow

The public side currently includes:

- `/register` for a multi-step student registration form
- `/sign-contract/{programEnrollment}` for contract signing

### API

The API exposes registration-related endpoints:

- `GET /api/branches`
- `GET /api/programs`
- `POST /api/register`

### Admin Panel

The admin panel is built with Filament and is available under:

- `/admin`

It includes resources for:

- program enrollments
- programs
- branches
- students
- parent accounts
- invoices, installments, and payments
- discounts and add-ons
- academic years
- users and roles

## Technology Stack

- PHP 8.4
- Laravel 12
- Filament 3
- Livewire 3
- Livewire Volt
- MySQL
- Tailwind CSS 3

Notable packages used in the project include:

- Filament Shield for admin permissions
- Spatie Model States for enrollment status workflows
- mPDF integration for PDF generation
- Filament Excel for exports
- Filament Autograph for signature-related workflows
- Thawani integration for payment handling

## Core Domain Models

The main entities in the system include:

- `Program`
- `Branch`
- `Student`
- `ParentAccount`
- `ProgramEnrollment`
- `Invoice`
- `Installment`
- `Payment`
- `Discount`
- `AcademicYear`

## Local Development

### Requirements

- PHP 8.2+
- Composer
- Node.js and npm
- MySQL

### Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Run The App

For the full local development workflow:

```bash
composer run dev
```

This starts:

- the Laravel development server
- the queue listener
- the Vite development server

If you prefer running services separately:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## Testing

Run the test suite with:

```bash
php artisan test
```

## Notes

- The default Laravel README has been replaced with project-specific documentation.
- Environment-specific values should be configured through `.env`.
- If frontend changes are not visible, ensure Vite is running with `npm run dev` or build assets with `npm run build`.
