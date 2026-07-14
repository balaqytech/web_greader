<?php

namespace App\Actions\Applications;

use App\Enums\GuardianRelationship;
use App\Exceptions\GuardianConflictException;
use App\Exceptions\StudentBranchConflictException;
use App\Models\Application;
use App\Models\Guardian;
use App\Models\Scopes\BranchScope;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Baseline acceptance against the flat `applications` schema (§3.5, §4.1): re-validates
 * completion, resolves the guardian and student from the denormalized columns, upserts
 * Guardian + Student + StudentContact rows, and back-links `applications.student_id`.
 *
 * Runs inside the caller's transaction. Student and guardian are looked up explicitly
 * without BranchScope and with a row lock so a returning student cannot be missed or
 * double-created under concurrency; an existing student in another branch is a hard
 * conflict (no silent cross-branch transfer). Contacts are fully synchronised so removed
 * or changed contacts and stale guardian flags never linger.
 */
class AcceptApplicationAction
{
    public function handle(Application $application): Student
    {
        return DB::transaction(function () use ($application) {
            app(ValidateApplicationCompletionAction::class)->handle($application);

            $guardian = $this->createOrUpdateGuardian($application);

            $student = $this->resolveStudent($application, $guardian);

            $this->syncStudentContacts($student, $application);

            $application->student()->associate($student);
            $application->save();

            return $student;
        });
    }

    private function createOrUpdateGuardian(Application $application): Guardian
    {
        [$name, $phone, $email, $idNumber, $occupation, $workAddress, $workPhone] = $this->resolveGuardianContact($application);

        $attributes = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'occupation' => $occupation,
            'work_address' => $workAddress,
            'work_phone' => $workPhone,
        ];

        $guardian = Guardian::where('id_number', $idNumber)->lockForUpdate()->first();

        // Explicit phone-uniqueness conflict: the phone already belongs to a *different*
        // guardian. Surfaced as a domain error rather than a raw integrity violation.
        $phoneOwner = Guardian::where('phone', $phone)
            ->when($guardian, fn ($query) => $query->whereKeyNot($guardian->getKey()))
            ->lockForUpdate()
            ->first();

        if ($phoneOwner) {
            throw GuardianConflictException::phone((string) $phone);
        }

        if ($guardian) {
            $guardian->update($attributes);

            return $guardian;
        }

        // lockForUpdate does not lock a row that does not exist, so a concurrent insert can
        // still win the race — convert the resulting unique violation into a domain error
        // (inside the acceptance transaction, so nothing partial is left behind).
        try {
            return Guardian::create($attributes + ['id_number' => $idNumber]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw GuardianConflictException::identity((string) $idNumber);
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }

    private function resolveStudent(Application $application, Guardian $guardian): Student
    {
        $civilNumber = $application->student_civil_number;

        $existing = Student::withoutGlobalScope(BranchScope::class)
            ->where('civil_number', $civilNumber)
            ->lockForUpdate()
            ->first();

        if ($existing && (int) $existing->branch_id !== (int) $application->branch_id) {
            throw StudentBranchConflictException::make(
                (string) $civilNumber,
                (int) $existing->branch_id,
                (int) $application->branch_id,
            );
        }

        $attributes = [
            'guardian_id' => $guardian->id,
            'branch_id' => $application->branch_id,
            'name' => $application->student_name,
            'gender' => $application->student_gender,
            'birth_date' => $application->student_birth_date,
            'state' => $application->student_state,
            'governorate' => $application->student_governorate,
            'village' => $application->student_village,
            'house_number' => $application->student_house_number,
            'parents_social_status' => $application->student_parents_social_status,
            'relationship_with_guardian' => $application->relationship_with_guardian,
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return Student::create($attributes + ['civil_number' => $civilNumber]);
    }

    /**
     * Full synchronisation: replace the student's contacts with exactly the set described
     * by the flat application, so stale rows and outdated guardian flags do not remain.
     */
    private function syncStudentContacts(Student $student, Application $application): void
    {
        $student->contacts()->delete();

        foreach ($this->contactRows($application) as $contact) {
            if (blank($contact['name'])) {
                continue;
            }

            $student->contacts()->create($contact);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string, 6: ?string}
     */
    private function resolveGuardianContact(Application $application): array
    {
        if ($application->father_is_guardian) {
            return $this->contactTuple($application, 'father');
        }

        if ($application->mother_is_guardian) {
            return $this->contactTuple($application, 'mother');
        }

        return $this->contactTuple($application, 'relative');
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string, 6: ?string}
     */
    private function contactTuple(Application $application, string $prefix): array
    {
        return [
            $application->{"{$prefix}_name"},
            $application->{"{$prefix}_phone"},
            $application->{"{$prefix}_email"},
            $application->{"{$prefix}_id_number"},
            $application->{"{$prefix}_occupation"},
            $application->{"{$prefix}_work_address"},
            $application->{"{$prefix}_work_phone"},
        ];
    }

    /**
     * Build StudentContact rows for each populated parent/relative on the flat application.
     *
     * @return array<int, array<string, mixed>>
     */
    private function contactRows(Application $application): array
    {
        $relativeIsGuardian = ! $application->father_is_guardian && ! $application->mother_is_guardian;

        return [
            $this->contactRow($application, 'father', GuardianRelationship::Father, (bool) $application->father_is_guardian),
            $this->contactRow($application, 'mother', GuardianRelationship::Mother, (bool) $application->mother_is_guardian),
            $this->contactRow(
                $application,
                'relative',
                $application->relationship_with_guardian ?? GuardianRelationship::Relative,
                $relativeIsGuardian,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactRow(Application $application, string $prefix, GuardianRelationship $relationship, bool $isGuardian): array
    {
        return [
            'relationship' => $relationship,
            'name' => $application->{"{$prefix}_name"},
            'phone' => $application->{"{$prefix}_phone"},
            'email' => $application->{"{$prefix}_email"},
            'id_number' => $application->{"{$prefix}_id_number"},
            'occupation' => $application->{"{$prefix}_occupation"},
            'work_address' => $application->{"{$prefix}_work_address"},
            'work_phone' => $application->{"{$prefix}_work_phone"},
            'is_guardian' => $isGuardian,
        ];
    }
}
