# Technical Capability Overview

This application is a Laravel 13 student registration and admissions system for a school-like domain. It combines an internal Filament admin panel, public/partner intake APIs, an affiliate portal, lead management, application review, contract signing, and dashboard reporting.

## Runtime and framework stack

- PHP 8.4 runtime target in the local Boost context.
- Laravel 13.4.
- MySQL database.
- Filament 5 admin panel.
- Livewire 4 and Flux UI 2 for first-party Blade/Livewire screens.
- Laravel Fortify for staff authentication, registration, email verification, password reset, and two-factor authentication.
- Laravel Sanctum for token-capable API authentication, though most public API endpoints are currently unauthenticated in `routes/api.php`.
- Spatie Laravel Model States for lead, application, and affiliate workflows.
- Spatie Webhook Server for outbound lead webhooks.
- Filament Shield and Spatie Permission for admin roles and permissions.
- OwenIt Laravel Auditing plus Tapp Filament Auditing for audit trails.
- Carlos Meneses Laravel mPDF for PDF generation.
- Akira Laravel QR Code for affiliate QR code downloads.
- Tailwind CSS 4 and Vite for frontend assets.
- Pest 4 for tests.

## User-facing surfaces

- `/admin`: Filament admin panel for operational staff.
- `/dashboard`: authenticated user dashboard route from the Livewire starter kit.
- `/affiliate`: affiliate portal with its own session guard.
- `/api/v1/*`: JSON endpoints for public registration intake, bot contacts, branches, programs, and reading assessments.
- `/contract/{token}`: public contract viewing and signing flow.
- `/settings`: authenticated staff profile/security settings powered by Livewire/Flux.

## Main business domains

- Branches: physical locations with active/inactive state and contact metadata.
- Programs: academic/summer program offerings with branch-specific pricing, birth-date limits, installment support, and contract templates.
- Seasons: academic/summer registration windows with active/closed lifecycle rules.
- Leads: incoming prospective-student records from website, dashboard, WhatsApp bot, affiliates, or other sources.
- Lead contacts: operational contact attempts and outcomes for leads.
- Applications: formal admissions records created from leads or manually in admin.
- Application contracts: one contract token/file/signature record per application.
- Students and guardians: accepted application output records.
- Affiliates: referral partners with verification workflow and QR/referral code support.
- Bot contacts: WhatsApp/bot conversation contacts and summaries.
- Reading assessment submissions: separate intake flow for reading assessment interest.
- Users/roles/permissions: staff identity and authorization.

## Admin panel capabilities

The Filament panel discovers resources and pages from `app/Filament`. It is configured in `app/Providers/Filament/AdminPanelProvider.php`.

Resource areas:

- Registration and admission: leads, applications, reading assessment submissions.
- School: students, guardians, seasons, programs.
- Affiliate and contacts: affiliates and bot contacts.
- Management and operations: branches.
- Financial and fees: placeholder/custom pages for payments, invoices, and affiliate payment requests.
- Roles and permissions: users and roles.

Dashboard widgets summarize registration volume by program type, program, branch, source, status, season, and date filters.

## Access control and tenancy behavior

- Staff users authenticate through the default `web` guard.
- Affiliates authenticate through a separate `affiliate` guard backed by the `affiliates` table.
- Admin access currently allows every authenticated `User` because `User::canAccessPanel()` returns `true`.
- `Lead`, `Application`, and `Student` use `BranchScope`, which filters records to the authenticated user's `branch_id` unless the user has the `super_admin` role.
- Filament Shield is installed for role/permission generation and enforcement in resources/policies.

## Integration points

- Lead-created webhook: configured under `services.webhooks.lead`.
- Affiliate verified webhook: configured under `services.webhooks.affiliate`.
- Contract signing: public tokenized link and optional PDF generation.
- QR code generation: affiliate QR code downloads embed `public/logo.png`.
- Public API consumers: website forms, bots, and other external systems can create leads, reading assessment submissions, and bot contacts.

## Test coverage areas

The test suite includes feature and unit coverage for:

- Staff authentication, registration, password reset, email verification, two-factor challenge, dashboard access, profile/security settings.
- Affiliate registration, login-related state, profile update, and password update.
- Lead API filtering, searching, counts, mother-phone handling, deduplication, and interested transitions.
- Lead identity normalization and reference number generation.
- Season create/open/close/update rules.
- Program Filament resource create/edit flows.
- Application workflow tests, though some appear to target older application sub-models that are absent from the current schema.
