# Application Actions

This document covers the action classes that drive the application lifecycle, from data entry through acceptance or rejection.

## State Flow

```
PendingRegistration → DataComplete → UnderReview → Accepted
                                                  → Rejected
                                                  → PendingRegistration (returned for correction)
```

## Actions

### UpdateApplicationDataAction

Updates the registration data on an existing application.

```php
use App\Actions\Applications\UpdateApplicationDataAction;
use App\DTOs\Application\UpdateApplicationDataDTO;

$dto = UpdateApplicationDataDTO::fromValidated($validatedData);

$application = app(UpdateApplicationDataAction::class)->execute($application, $dto);
```

- Fills the application with the DTO fields and saves.
- Does **not** change the application state.
- Use this while the application is in `PendingRegistration` to populate student, parent, and relative fields.

---

### SubmitApplicationForReviewAction

Validates required fields and transitions the application from `PendingRegistration` through `DataComplete` to `UnderReview` in a single call.

```php
use App\Actions\Applications\SubmitApplicationForReviewAction;

$application = app(SubmitApplicationForReviewAction::class)->execute(
    application: $application,
    transitionedBy: $user->id, // optional
    notes: 'Ready for review',  // optional
);
```

**Validation rules enforced (all required for DataComplete):**

| Section | Required Fields |
|---|---|
| Student | `student_name`, `student_gender`, `student_birth_date`, `student_civil_number`, `student_state`, `student_governorate`, `student_village`, `student_house_number`, `student_parents_social_status` |
| Father | `father_name`, `father_phone`, `father_id_number` |
| Mother | `mother_name`, `mother_phone`, `mother_id_number` |
| Relative | `relative_name`, `relative_phone` |
| Guardian | `father_is_guardian` or `mother_is_guardian` must be `true` |

Throws `Illuminate\Validation\ValidationException` if any required data is missing.

---

### AcceptApplicationAction

Accepts an application that is `UnderReview`. This creates a **Guardian** and a **Student** record automatically.

```php
use App\Actions\Applications\AcceptApplicationAction;

$application = app(AcceptApplicationAction::class)->execute(
    application: $application,
    transitionedBy: $user->id, // optional
    notes: 'Meets all criteria', // optional
);

// Access the created records
$student = $application->student;
$guardian = $application->student->guardian;
```

**What happens internally:**

1. Transitions the application to `Accepted`.
2. Determines the guardian based on the `father_is_guardian` / `mother_is_guardian` flag.
3. Creates or reuses a `Guardian` record — **deduplicated by `id_number`** (civil ID). If a guardian with the same `id_number` already exists, that record is reused.
4. Creates a `Student` record linked to the guardian, application, branch, season, and program.

All of this runs inside a single database transaction.

---

### RejectApplicationAction

Rejects an application that is `UnderReview`.

```php
use App\Actions\Applications\RejectApplicationAction;

$application = app(RejectApplicationAction::class)->execute(
    application: $application,
    rejectionReason: 'Does not meet age criteria.',
    transitionedBy: $user->id, // optional
);

// $application->rejection_reason contains the reason
```

---

### ReturnApplicationForCorrectionAction

Returns an application that is `UnderReview` back to `PendingRegistration` so the user can fix missing or incorrect data.

```php
use App\Actions\Applications\ReturnApplicationForCorrectionAction;

$application = app(ReturnApplicationForCorrectionAction::class)->execute(
    application: $application,
    notes: 'Missing student civil number.',
    transitionedBy: $user->id, // optional
);
```

After this, the application is back in `PendingRegistration` and can be edited again with `UpdateApplicationDataAction`, then resubmitted with `SubmitApplicationForReviewAction`.

## Activity Log

Every state transition is recorded in the `application_activities` table. Each entry stores:

- `from_state` / `to_state` — the transition that occurred
- `transitioned_by` — the user ID who triggered it (nullable for system transitions)
- `notes` — optional notes or rejection reason
- `transitioned_at` — timestamp

Access via:

```php
$application->activities; // Collection, ordered by most recent first
```
