# Domain Capabilities

This document describes application behavior from a product and backend workflow perspective.

## Admissions intake

The system accepts registration interest as leads. A lead captures the guardian name, student name, WhatsApp number, target branch, target program, active season, source, optional mother phone, optional affiliate code, and flexible JSON metadata.

Lead intake capabilities:

- Public `POST /api/v1/leads` endpoint.
- Admin-side lead resource.
- Current active season resolution based on the selected program type.
- Program availability checks when moving a lead toward application creation.
- Duplicate prevention through normalized phone, program, season, branch, and student identity fingerprint.
- Search and filtering across first-class columns and selected `data` JSON keys.
- Lead count summary endpoint grouped by branch and program.

## Lead follow-up

Operational staff can transition leads through contact states and record contact attempts.

Lead contact data includes:

- Contact method.
- Contact result.
- Contacted-by value.
- Notes.
- Follow-up date.
- Affiliate snapshot data where applicable.

When a lead becomes interested, the application attempts to create a draft application and prefill any available data.

## Applications

Applications represent formal admissions records. They are linked to a lead, season, program, branch, and optional affiliate.

Application capabilities:

- Draft creation from leads or Filament forms.
- Full student details stored on the application row.
- Father, mother, and relative contact sections stored on the application row.
- Guardian resolution through `father_is_guardian` or `mother_is_guardian`, falling back to relative fields for computed guardian name/phone.
- State-driven movement from draft to submitted, contract signature, review, acceptance/rejection, or cancellation.
- Activity logging for state transitions.
- Rejection reason storage.
- Unique application reference numbers and per-season student civil-number uniqueness.

## Contracts

Contract data is represented by `application_contracts`.

Contract capabilities:

- One contract per application.
- Generated token and expiry for public signing.
- Public contract rendering from the program contract template.
- Template variable replacement for program name, parent name, student name, enrollment date, and branch price.
- Signature capture as base64 PNG.
- Signature file storage.
- Contract PDF generation from `resources/views/pdf/contract.blade.php`.
- Signed file upload by staff through Filament.

## Students and guardians

Accepted applications are intended to produce guardian and student records.

Student/guardian capabilities:

- Guardian records store name, phone, email, civil ID, occupation, work address, and work phone.
- Student records store guardian, branch, name, gender, birth date, civil number, address, and parent social status.
- Student contacts store parent/relative contact records.
- Students and guardians are manageable through Filament resources.

Review note: several current action/test files refer to older `ApplicationStudent` and `ApplicationContact` models, but the active database schema stores application details directly on the `applications` table.

## Programs and branches

Programs define available registration products.

Program capabilities:

- Academic/summer type.
- Active/open flags.
- Sort order.
- Birth-date range limits.
- Installment support flag.
- Long-form contract template.
- Branch-specific availability and price through `program_branch`.
- API listing with optional type and branch filters.

Branches define school locations.

Branch capabilities:

- Name, address, governorate, phone, mobile, active flag, and JSON additional info.
- Active branch filtering in public API.
- Branch-level data scoping for staff users.

## Seasons

Seasons determine current active intake windows for academic and summer programs.

Season capabilities:

- Academic/summer type.
- Start and end dates.
- Active flag.
- Permanent close timestamp.
- Rules to prevent more than one active season per type.
- Rules to allow one active academic and one active summer season at the same time.

## Affiliates

Affiliates are referral partners.

Affiliate capabilities:

- Public registration with name, WhatsApp, and password.
- Dedicated login guard and portal.
- Verification requirement before portal access.
- Admin verification/rejection workflow.
- Affiliate code generation.
- Lead/application attribution through `affiliate_id` and code snapshots.
- QR code download for referral URL distribution.
- Profile and password self-service.

## Bot contacts and reading assessments

Bot contacts capture WhatsApp or chatbot conversations that may not yet be leads.

Bot contact capabilities:

- API creation with unique WhatsApp number.
- Conversation summary, rejection reason, notes, additional data, and metadata fields.
- Filament table page for operational review.

Reading assessment submissions capture a separate assessment-interest workflow.

Reading assessment capabilities:

- API creation and listing.
- Student name, age, grade level, guardian, WhatsApp, branch, source, and additional info.
- WhatsApp and student-name normalization.
- Branch relationship and resource serialization.
