# Review Brief for ChatGPT

Use this file as the entry point when asking another model to analyze this codebase.

## What this app does

The app manages school admissions from initial lead capture through application review and contract signing. It includes staff administration, affiliate referrals, public intake APIs, bot contact capture, reading assessment submissions, branch/program/season management, and student/guardian record creation after acceptance.

## Suggested review focus

1. State-machine correctness
   - Verify all configured Spatie state transitions reference existing classes.
   - Confirm application acceptance/rejection transitions are intentionally enabled or disabled.
   - Check whether transition actions validate business prerequisites consistently.

2. Application data model consistency
   - The current database stores application student/contact data on `applications`.
   - Several current PHP files/tests reference `ApplicationStudent`, `ApplicationContact`, and `ContactType`, which are absent from the file tree and schema.
   - Decide whether to restore split models/tables or refactor code to the denormalized application schema.

3. Contract workflow
   - Align `WaitingContract`, `WaitingContractSignature`, contract token storage, online signature, and staff-upload paths.
   - Ensure token expiry is checked in both GET and POST contract routes.
   - Confirm signed-contract validation before `UnderReview` transitions.

4. API security
   - Public endpoints can list and create leads, bot contacts, branches, programs, and reading assessment submissions.
   - Review whether any endpoints need Sanctum, signed requests, rate limiting, CORS restrictions, or webhook signature verification.

5. Admin authorization
   - `User::canAccessPanel()` currently returns `true` for every authenticated staff user.
   - Branch scoping exists for leads, applications, and students, but resource policies and relation managers should be reviewed for bypasses.

6. Data integrity
   - Review uniqueness and deduplication rules for leads, applications, students, affiliates, bot contacts, and reading assessments.
   - Check whether `updateOrCreate($data)` in reading assessment creation has the intended lookup behavior.
   - Confirm application reference generation is concurrency-safe enough for production.

7. External integrations
   - Review outbound webhook defaults and secrets.
   - Ensure production-only behavior for lead webhook dispatch matches operational expectations.
   - Verify QR referral URL host is configurable instead of hard-coded.

8. Localization and encoding
   - The app has Arabic and English language resources.
   - Some files displayed mojibake when read in the local terminal; confirm repository encoding and source strings are valid UTF-8.

## High-signal files to inspect first

- `app/States/Applications/ApplicationState.php`
- `app/Actions/Applications/*`
- `app/Models/Application.php`
- `app/Models/Lead.php`
- `app/Actions/Leads/CreateLeadAction.php`
- `app/Services/LeadDuplicateResolver.php`
- `app/Support/LeadIdentityNormalizer.php`
- `app/Http/Controllers/Api/V1/*`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Models/Scopes/BranchScope.php`
- `routes/api.php`
- `routes/affiliate.php`
- `routes/web.php`
- `database/migrations/*applications*`
- `database/migrations/*leads*`
- `tests/Feature/ApplicationWorkflowTest.php`

## Known discrepancies found during documentation

- `app/States/Applications/ApplicationState.php` imports `WaitingContractSignatureToCancelled`, but no such file was found.
- `SendContractAction` and `UploadSignedContractAction` refer to `WaitingContract`, but the current state class is `WaitingContractSignature`.
- `SendContractAction` builds a URL from `$application->contract_token`, but the schema stores contract tokens on `application_contracts.token`.
- `AcceptApplicationAction` references `ApplicationContact` and `ApplicationStudent`, which are missing.
- `ApplicationFactory` and `ApplicationWorkflowTest` also reference absent application sub-models.
- `Student::applications()` references `ApplicationStudent`, which is missing.
- `LeadController` registers `POST /api/v1/leads/{lead}/transition`, but no `transition` method exists in the displayed controller.
- `ApplicationState` has acceptance/rejection transitions commented out while Filament actions expect them through `canTransitionTo`.

## Questions for reviewers

- Should applications use a normalized sub-model design (`ApplicationStudent`, `ApplicationContact`) or the current single-table application design?
- Should public API intake be unauthenticated, signed, or rate-limited by source?
- Is branch scoping meant to apply only to staff UI queries, or also to API/admin background actions?
- Should affiliate referral attribution require verified affiliates only?
- Should accepting an application create/update students and guardians inside the state transition, or as an explicit service called by the Filament action?
- Are financial pages placeholders, or should payments/invoices become first-class domain models?
