# Application Actions and State Machines

This document summarizes the current action classes and state transitions that drive the registration lifecycle.

## Lead lifecycle

Primary files:

- `app/Actions/Leads/CreateLeadAction.php`
- `app/Actions/Leads/TransitionLeadStateAction.php`
- `app/States/Leads/LeadState.php`
- `app/Support/LeadIdentityNormalizer.php`
- `app/Services/LeadDuplicateResolver.php`

Configured lead states:

```text
NewLead
  -> ContactedLead
  -> Interested
  -> NotInterested
  -> NoResponse

ContactedLead
  -> Interested
  -> NotInterested
  -> NoResponse
```

Capabilities:

- Creates leads from the public API, Filament, or bot-facing flows.
- Normalizes WhatsApp numbers and Eastern Arabic numerals before persistence.
- Generates daily sequential lead reference numbers through `LeadRefNoGenerator`.
- Computes normalized student-name identity fingerprints to merge likely duplicate leads.
- Keeps siblings separate when the same WhatsApp number is used with clearly different student names.
- Stores flexible extra lead data in `leads.data` JSON while promoting `mother_phone` into a first-class column.
- Links leads to affiliates through an optional affiliate code snapshot.
- Can dispatch a lead-created webhook in production when `services.webhooks.lead.enabled` is enabled.
- When a lead becomes `Interested`, the system attempts to create a draft application after checking the program is available in the selected branch.

## Application lifecycle

Primary files:

- `app/Models/Application.php`
- `app/Actions/Applications/CreateApplicationAction.php`
- `app/Actions/Applications/ConvertLeadToApplicationAction.php`
- `app/Actions/Applications/GenerateApplicationContractAction.php`
- `app/Actions/Applications/SignContractOnlineAction.php`
- `app/Actions/Applications/UploadSignedContractAction.php`
- `app/States/Applications/ApplicationState.php`

Configured application states in `ApplicationState`:

```text
Draft
  -> Submitted
  -> Cancelled

Submitted
  -> WaitingContractSignature
  -> Cancelled

WaitingContractSignature
  -> Submitted
  -> UnderReview

UnderReview
  -> Accepted    (transition classes exist but are currently commented out)
  -> Rejected    (transition classes exist but are currently commented out)
```

Capabilities:

- Creates applications with generated `APP-{year}{sequence}` reference numbers.
- Stores application form data directly on the `applications` table, including student, father, mother, and relative fields.
- Converts interested leads into draft applications and can prefill data from existing students when found.
- Generates one contract row per application with a 64-character token and 7-day expiry.
- Exposes a public contract-signing flow at `/contract/{token}`.
- Accepts base64 PNG signatures, stores signature files on the public disk, generates a contract PDF, and moves applications to review after online signing.
- Supports staff upload of signed contract files through Filament actions.
- Records application transitions in `application_activities`.

## Affiliate lifecycle

Primary files:

- `app/Models/Affiliate.php`
- `app/States/Affiliates/AffiliateState.php`
- `app/Http/Controllers/Affiliate/*`
- `app/Filament/Resources/Affiliates/*`

Configured affiliate states:

```text
Pending
  -> Verified
  -> Rejected

Verified
  -> Rejected

Rejected
  -> Verified
```

Capabilities:

- Public affiliate registration by name, WhatsApp, and password.
- Affiliate login through a dedicated `affiliate` session guard.
- Blocks login until the affiliate is verified.
- Lets verified affiliates access a dashboard, update profile information, update password, and download a referral QR code.
- Tracks affiliate-linked leads and applications.
- Generates affiliate codes from a name prefix plus random digits.
- Audits affiliate records through the configured auditing package.

## Season lifecycle

Primary files:

- `app/Actions/Season/CreateSeason.php`
- `app/Actions/Season/OpenSeason.php`
- `app/Actions/Season/CloseSeason.php`
- `app/Actions/Season/UpdateSeason.php`
- `app/Rules/Season/SeasonRules.php`

Capabilities:

- Supports academic and summer season types.
- Keeps at most one active season per program type.
- Allows one active academic season and one active summer season to coexist.
- Creates new seasons as active only when the relevant active slot is available.
- Permanently closes seasons by setting `closed_at` and making them inactive.

## Current implementation gaps to review

- `ApplicationState` imports `WaitingContractSignatureToCancelled`, but that transition file is not present.
- `SendContractAction` and `UploadSignedContractAction` reference `WaitingContract`, while the current state class is `WaitingContractSignature`.
- `SendContractAction` references `Application::$contract_token`, but contract tokens currently live on `application_contracts.token`.
- `AcceptApplicationAction`, `ApplicationFactory`, `Student::applications()`, and some tests reference `ApplicationStudent`, `ApplicationContact`, and `ContactType`, which are not present in the current file tree or database schema.
- `ApplicationState` currently comments out transitions from `UnderReview` to `Accepted` and `Rejected`, while Filament actions and tests still expect those transitions to be available.
- `WaitingContractSignatureToUnderReview` does not itself validate that a contract has been signed; the online signing action performs that validation before transitioning.
