# API Capabilities

Base path: `/api/v1`.

Most endpoints in `routes/api.php` are public unless explicitly protected. Only `/api/v1/user` uses `auth:sanctum`.

## Branches

### `GET /api/v1/branches`

Returns active branches.

Query parameters:

- `name`: optional partial branch-name search.

Response resource fields:

- `id`
- `name`
- `address`
- `governorate`
- `phone`
- `mobile`
- `additional_info`

### `GET /api/v1/branches/{branch}`

Returns one active branch or 404.

## Programs

### `GET /api/v1/programs`

Returns active programs.

Query parameters:

- `type`: optional program type filter.
- `branch_id`: optional filter for programs available in a branch.

Response resource fields:

- `id`
- `name`
- `type`
- `description`
- `min_birth_date`
- `max_birth_date`
- `is_open`
- `branches[]` with `id`, `name`, `governorate`, and `price`.

### `GET /api/v1/programs/{program}`

Returns one program.

## Leads

### `GET /api/v1/leads`

Returns paginated leads with branch, program, season, and affiliate data.

Supported query parameters:

- `whatsapp`
- `program_id`
- `branch_id`
- `status`
- `source`
- `data[key]` for exact JSON-field filtering. `data[mother_phone]` maps to the first-class `mother_phone` column.
- `search`
- `search_fields[]=data.key` to include selected JSON keys in text search.
- `created_from`
- `created_to`

Pagination:

- Fixed page size of 15.
- Query string is appended to pagination links.

### `POST /api/v1/leads`

Creates or merges a lead.

Required fields:

- `whatsapp`
- `program_id`
- `branch_id`
- `guardian_name`
- `student_name`
- `source`

Optional fields:

- `affiliate_code`
- `mother_phone`
- `data`: object of extra fields.

Behavior:

- Normalizes phone number input.
- Resolves the current season from the selected program type.
- Deduplicates against existing leads by phone, program, season, branch, and normalized student identity.
- Stores affiliate attribution when the code matches an affiliate.

### `GET /api/v1/leads/counts`

Returns aggregate counts for reporting.

Supported query parameters:

- `branch_id`
- `program_id`
- `season_id`
- `status`
- `source`
- `created_from`
- `created_to`

Response shape:

- `data.total_leads`
- `data.branches[]` with `branch_id`, `branch_name`, and `leads_count`.
- `data.programs_by_branch[]` with `branch_id`, `branch_name`, `program_id`, `program_name`, and `leads_count`.

### `POST /api/v1/leads/{lead}/transition`

Route is registered but the current `LeadController` file does not define a `transition` method. Review this before exposing or documenting client usage.

## Reading assessment submissions

### `GET /api/v1/reading-assessment-form-submissions`

Returns paginated submissions.

Supported query parameters:

- `branch_id`
- `whatsapp`

### `POST /api/v1/reading-assessment-form-submissions`

Creates a reading assessment submission.

Required fields:

- `student_name`
- `age` between 4 and 13.
- `grade_level`
- `guardian_name`
- `whatsapp`
- `branch_id`
- `source`

Optional fields:

- `additional_info`: object.

Behavior:

- Normalizes WhatsApp.
- Normalizes student name.
- Uses `updateOrCreate` with the submitted payload.

### `GET /api/v1/reading-assessment-form-submissions/{submission}`

Returns one submission with branch data.

## Bot contacts

### `GET /api/v1/bot-contacts`

Returns paginated bot contacts, newest first.

### `POST /api/v1/bot-contacts`

Creates a bot contact.

Required fields:

- `channel`
- `whatsapp`

Optional fields:

- `sender_name`
- `conversation_summary`
- `rejection_reason`
- `notes`
- `additional_data`: object.

Behavior:

- Enforces unique WhatsApp numbers.
- Returns 422 with a custom message on duplicate WhatsApp.

### `GET /api/v1/bot-contacts/{bot_contact}`

Returns one bot contact.

## Authenticated user

### `GET /api/v1/user`

Requires Sanctum authentication and returns the authenticated request user.

## Contract signing web routes

These are not under `/api/v1`, but they are public integration endpoints.

### `GET /contract/{token}`

Loads an unsigned contract by token and renders the contract signing page.

### `POST /contract/{token}`

Accepts a `signature` field starting with `data:image/png;base64,`, stores the signature/PDF, updates the contract, and moves the application to review when valid.
