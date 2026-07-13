<?php

namespace App\Actions\Applications;

use App\Enums\GuardianRelationship;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentContact;
use Illuminate\Support\Facades\DB;

/**
 * Baseline acceptance against the flat `applications` schema (§3.5, §4.1): resolves the
 * guardian and student from the denormalized columns, upserts Guardian + Student +
 * StudentContact rows, and back-links `applications.student_id`. Runs inside the caller's
 * transaction so a mid-transaction failure rolls student, guardian, contacts, student_id,
 * and the application state back together.
 */
class AcceptApplicationAction
{
    public function handle(Application $application): Student
    {
        return DB::transaction(function () use ($application) {
            if (blank($application->student_civil_number)) {
                throw new ApplicationIncompleteException(__('alerts.application.student_civil_number_required'));
            }

            $guardian = $this->createOrUpdateGuardian($application);

            $student = $this->createOrUpdateStudent($application, $guardian);

            $this->syncStudentContacts($student, $application);

            $application->student()->associate($student);
            $application->save();

            return $student;
        });
    }

    private function createOrUpdateGuardian(Application $application): Guardian
    {
        [$name, $phone, $email, $idNumber, $occupation, $workAddress, $workPhone] = $this->resolveGuardianContact($application);

        if (blank($idNumber)) {
            throw new ApplicationIncompleteException(__('alerts.application.guardian_required'));
        }

        return Guardian::updateOrCreate(
            ['id_number' => $idNumber],
            [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'occupation' => $occupation,
                'work_address' => $workAddress,
                'work_phone' => $workPhone,
            ]
        );
    }

    private function createOrUpdateStudent(Application $application, Guardian $guardian): Student
    {
        return Student::updateOrCreate(
            ['civil_number' => $application->student_civil_number],
            [
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
            ]
        );
    }

    private function syncStudentContacts(Student $student, Application $application): void
    {
        foreach ($this->contactRows($application) as $contact) {
            if (blank($contact['name'])) {
                continue;
            }

            $lookup = ['student_id' => $student->id];

            if (filled($contact['id_number'])) {
                $lookup['id_number'] = $contact['id_number'];
            } else {
                $lookup['relationship'] = $contact['relationship'];
                $lookup['name'] = $contact['name'];
            }

            StudentContact::updateOrCreate($lookup, $contact + ['student_id' => $student->id]);
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
