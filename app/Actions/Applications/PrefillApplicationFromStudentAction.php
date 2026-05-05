<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class PrefillApplicationFromStudentAction
{
    public function handle(Application $application, Student $student): void
    {
        DB::transaction(function () use ($application, $student) {
            $application->applicationStudent()->updateOrCreate(
                ['application_id' => $application->id],
                [
                    'name' => $student->name,
                    'gender' => $student->gender,
                    'birth_date' => $student->birth_date,
                    'civil_number' => $student->civil_number,
                    'state' => $student->state,
                    'governorate' => $student->governorate,
                    'village' => $student->village,
                    'house_number' => $student->house_number,
                    'parents_social_status' => $student->parents_social_status,
                ]
            );

            foreach ($student->contacts as $contact) {
                // Use id_number as primary lookup key when available (required for guardian, recommended for others).
                $lookup = $contact->id_number
                    ? ['application_id' => $application->id, 'id_number' => $contact->id_number]
                    : ['application_id' => $application->id, 'type' => $contact->type, 'phone' => $contact->phone];

                $application->contacts()->updateOrCreate(
                    $lookup,
                    [
                        'type' => $contact->type,
                        'relationship' => $contact->relationship,
                        'name' => $contact->name,
                        'phone' => $contact->phone,
                        'email' => $contact->email,
                        'id_number' => $contact->id_number,
                        'occupation' => $contact->occupation,
                        'work_address' => $contact->work_address,
                        'work_phone' => $contact->work_phone,
                        'is_guardian' => $contact->is_guardian,
                    ]
                );
            }
        });
    }
}
