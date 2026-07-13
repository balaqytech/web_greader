# Data Model Summary

This document summarizes the main persisted entities and relationships visible in the current schema and models.

## Core tables

### `branches`

Stores school/location data.

Important columns:

- `name`
- `address`
- `governorate`
- `phone`
- `mobile`
- `is_active`
- `additional_info` JSON

Relationships:

- Many-to-many with `programs` through `program_branch`.
- Has many `applications`.

### `programs`

Stores program offerings.

Important columns:

- `name`
- `description`
- `type`
- `accept_installments`
- `min_birth_date`
- `max_birth_date`
- `contract`
- `is_open`
- `is_active`
- `sort_order`

Relationships:

- Many-to-many with `branches` through `program_branch`, with `price`.
- Has many `program_rules`.

### `seasons`

Stores registration seasons.

Important columns:

- `name`
- `type`
- `start_date`
- `end_date`
- `is_active`
- `closed_at`

Behavior:

- `Season::current(ProgramType $program_type)` returns the active season for a program type.

### `leads`

Stores incoming prospective-student records.

Important columns:

- `ref_no`
- `branch_id`
- `season_id`
- `program_type`
- `program_id`
- `whatsapp`
- `mother_phone`
- `guardian_name`
- `student_name`
- `student_name_normalized`
- `identity_fingerprint`
- `data` JSON
- `status`
- `source`
- `affiliate_id`
- `affiliate_code_snapshot`

Indexes and constraints:

- Unique `ref_no`.
- Unique identity index over `whatsapp`, `program_id`, `season_id`, `branch_id`, and `identity_fingerprint`.
- Lookup index over `whatsapp`, `program_id`, `season_id`, and `branch_id`.
- JSON validity check on `data`.

Relationships:

- Belongs to branch, season, program, and affiliate.
- Has many lead contacts.
- Has one application.

### `lead_contacts`

Stores follow-up attempts for leads.

Important columns:

- `lead_id`
- `contacted_by`
- `contact_method`
- `contact_result`
- `notes`
- `follow_up_at`
- `contacted_at`
- `affiliate_id`
- `affiliate_code_snapshot`

### `applications`

Stores formal admissions applications.

Important columns:

- `ref_no`
- `lead_id`
- `season_id`
- `program_id`
- `branch_id`
- `affiliate_id`
- `status`
- Student fields: `student_name`, `student_gender`, `student_birth_date`, `student_civil_number`, address/social-status fields.
- Father fields: name, phone, email, civil ID, occupation, work address, work phone, `father_is_guardian`.
- Mother fields: name, phone, email, civil ID, occupation, work address, work phone, `mother_is_guardian`.
- Relative fields: name, phone, email, civil ID, occupation, work address, work phone.
- `rejection_reason`

Indexes and constraints:

- Unique `ref_no`.
- Unique `lead_id`.
- Unique `student_civil_number` plus `season_id`.
- Indexed `status` and `student_gender`.

Relationships:

- Belongs to lead, season, program, branch, and affiliate.
- Has one application contract.
- Has many application activities.

Design note:

- The active schema stores application student/contact details directly on the `applications` table. Some code still references absent split models such as `ApplicationStudent` and `ApplicationContact`.

### `application_contracts`

Stores contract signing metadata.

Important columns:

- `application_id`
- `token`
- `token_expires_at`
- `signed_at`
- `signed_by_applicant`
- `file_path`
- `signature_path`

Constraints:

- Unique `application_id`.
- Unique `token`.
- Cascades delete when the parent application is deleted.

### `application_activities`

Stores application state-transition history.

Important columns:

- `application_id`
- `transitioned_by`
- `from_state`
- `to_state`
- `notes`
- `transitioned_at`

### `guardians`

Stores accepted guardian records.

Important columns:

- `name`
- `phone`
- `email`
- `id_number`
- `occupation`
- `work_address`
- `work_phone`

Relationships:

- Has many students.

### `students`

Stores accepted student records.

Important columns:

- `guardian_id`
- `branch_id`
- `name`
- `gender`
- `birth_date`
- `civil_number`
- `state`
- `governorate`
- `village`
- `house_number`
- `parents_social_status`
- `relationship_with_guardian`

Relationships:

- Belongs to guardian and branch.
- Has many student contacts.

### `student_contacts`

Stores contact records associated with accepted students.

Important columns:

- `student_id`
- `relationship`
- `name`
- `phone`
- `email`
- `id_number`
- `occupation`
- `work_address`
- `work_phone`
- `is_guardian`

## Affiliate and intake tables

### `affiliates`

Stores referral partners.

Important columns:

- `name`
- `code`
- `category`
- `whatsapp`
- `password`
- `email`
- `status`
- `notes`
- `verified_by`
- `verified_at`
- `rejected_by`
- `rejected_at`
- `creation_source`

Relationships:

- Has many leads.
- Has many applications.
- Belongs to verifier/rejecter users.

### `bot_contacts`

Stores bot-origin contacts.

Important columns:

- `channel`
- `sender_name`
- `whatsapp`
- `status`
- `conversation_summary`
- `rejection_reason`
- `notes`
- `additional_data` JSON
- `metadata` JSON
- soft deletes

### `reading_assessment_form_submissions`

Stores reading assessment interest submissions.

Important columns:

- `student_name`
- `age`
- `grade_level`
- `guardian_name`
- `whatsapp`
- `branch_id`
- `status`
- `source`
- `additional_info` JSON

Relationships:

- Belongs to branch.

## Auth and authorization tables

- `users`: staff users with optional `branch_id`, Fortify two-factor fields, and Sanctum support.
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`: Spatie Permission/Filament Shield tables.
- `personal_access_tokens`: Sanctum tokens.
- `password_reset_tokens`, `sessions`: framework auth/session tables.
- `audits`: model audit trail.

## Cross-cutting scopes and traits

- `BranchScope` limits branch-owned models by authenticated user's branch unless the user has `super_admin`.
- `HasWhatsapp` normalizes phone-like fields.
- `HasAffiliate` centralizes affiliate relationships/snapshots.
- `HasNormalizedStudentName` maintains normalized identity fields.
