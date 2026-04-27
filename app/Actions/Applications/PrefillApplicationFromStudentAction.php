<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\Models\Student;

final class PrefillApplicationFromStudentAction
{
    public function execute(Application $application, Student $student): Application
    {
        $father = $student->father_data ?? [];
        $mother = $student->mother_data ?? [];
        $relative = $student->relative_data ?? [];

        $application->fill([
            // Student data — fill only missing
            'student_gender' => $application->student_gender ?? $student->gender,
            'student_birth_date' => $application->student_birth_date ?? $student->birth_date,
            'student_civil_number' => $application->student_civil_number ?? $student->civil_number,
            'student_state' => $application->student_state ?? $student->state,
            'student_governorate' => $application->student_governorate ?? $student->governorate,
            'student_village' => $application->student_village ?? $student->village,
            'student_house_number' => $application->student_house_number ?? $student->house_number,
            'student_parents_social_status' => $application->student_parents_social_status ?? $student->parents_social_status,

            // Father data — fill only missing
            'father_name' => $application->father_name ?? ($father['name'] ?? null),
            'father_phone' => $application->father_phone ?? ($father['phone'] ?? null),
            'father_email' => $application->father_email ?? ($father['email'] ?? null),
            'father_id_number' => $application->father_id_number ?? ($father['id_number'] ?? null),
            'father_occupation' => $application->father_occupation ?? ($father['occupation'] ?? null),
            'father_work_address' => $application->father_work_address ?? ($father['work_address'] ?? null),
            'father_work_phone' => $application->father_work_phone ?? ($father['work_phone'] ?? null),
            'father_is_guardian' => $application->father_is_guardian ?: ($father['is_guardian'] ?? false),

            // Mother data — fill only missing
            'mother_name' => $application->mother_name ?? ($mother['name'] ?? null),
            'mother_phone' => $application->mother_phone ?? ($mother['phone'] ?? null),
            'mother_email' => $application->mother_email ?? ($mother['email'] ?? null),
            'mother_id_number' => $application->mother_id_number ?? ($mother['id_number'] ?? null),
            'mother_occupation' => $application->mother_occupation ?? ($mother['occupation'] ?? null),
            'mother_work_address' => $application->mother_work_address ?? ($mother['work_address'] ?? null),
            'mother_work_phone' => $application->mother_work_phone ?? ($mother['work_phone'] ?? null),
            'mother_is_guardian' => $application->mother_is_guardian ?: ($mother['is_guardian'] ?? false),

            // Relative data — fill only missing
            'relative_name' => $application->relative_name ?? ($relative['name'] ?? null),
            'relative_phone' => $application->relative_phone ?? ($relative['phone'] ?? null),
            'relative_email' => $application->relative_email ?? ($relative['email'] ?? null),
            'relative_id_number' => $application->relative_id_number ?? ($relative['id_number'] ?? null),
            'relative_occupation' => $application->relative_occupation ?? ($relative['occupation'] ?? null),
            'relative_work_address' => $application->relative_work_address ?? ($relative['work_address'] ?? null),
            'relative_work_phone' => $application->relative_work_phone ?? ($relative['work_phone'] ?? null),
        ]);

        $application->save();

        return $application;
    }
}
