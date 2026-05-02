<?php

namespace App\Actions\Applications;

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\ApplicationStudent;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentContact;
use Illuminate\Support\Facades\DB;

class AcceptApplicationAction
{
    public function handle(Application $application): Student
    {
        return DB::transaction(function () use ($application) {
            $application->loadMissing([
                'applicationStudent',
                'contacts',
                'contract',
            ]);

            $applicationStudent = $application->applicationStudent;

            if (! $applicationStudent) {
                throw new ApplicationIncompleteException('Student information is missing.');
            }

            $guardianContact = $application->contacts
                ->firstWhere('is_guardian', true);

            if (! $guardianContact) {
                throw new ApplicationIncompleteException('Guardian information is missing.');
            }

            $guardian = $this->createOrUpdateGuardian($guardianContact);

            $student = $this->createOrUpdateStudent($applicationStudent, $guardian);

            $this->syncStudentContacts($student, $application);

            $application->update([
                'student_id' => $student->id,
            ]);

            return $student;
        });
    }

    private function createOrUpdateGuardian(ApplicationContact $contact): Guardian
    {
        // id_number is required for guardian contacts — enforced during submission validation.
        return Guardian::updateOrCreate(
            ['id_number' => $contact->id_number],
            [
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'id_number' => $contact->id_number,
                'occupation' => $contact->occupation,
                'work_address' => $contact->work_address,
                'work_phone' => $contact->work_phone,
            ]
        );
    }

    private function createOrUpdateStudent(
        ApplicationStudent $applicationStudent,
        Guardian $guardian
    ): Student {
        // civil_number is required — enforced during submission validation.
        return Student::updateOrCreate(
            ['civil_number' => $applicationStudent->civil_number],
            [
                'guardian_id' => $guardian->id,
                'name' => $applicationStudent->name,
                'gender' => $applicationStudent->gender,
                'birth_date' => $applicationStudent->birth_date,
                'civil_number' => $applicationStudent->civil_number,
                'state' => $applicationStudent->state,
                'governorate' => $applicationStudent->governorate,
                'village' => $applicationStudent->village,
                'house_number' => $applicationStudent->house_number,
                'parents_social_status' => $applicationStudent->parents_social_status,
            ]
        );
    }

    private function syncStudentContacts(Student $student, Application $application): void
    {
        foreach ($application->contacts as $contact) {
            StudentContact::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'type' => $contact->type,
                    'id_number' => $contact->id_number,
                ],
                [
                    'relationship' => $contact->relationship,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'occupation' => $contact->occupation,
                    'work_address' => $contact->work_address,
                    'work_phone' => $contact->work_phone,
                    'is_guardian' => $contact->is_guardian,
                ]
            );
        }
    }
}
