<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Lead;
use App\Models\Student;

final class ExistingStudentLookupService
{
    /**
     * Find an existing student matching a lead's guardian phone + student name.
     */
    public function findExistingStudent(Lead $lead): ?Student
    {
        $guardianIds = Guardian::where('phone', $lead->whatsapp)->pluck('id');

        if ($guardianIds->isEmpty()) {
            return null;
        }

        return Student::whereIn('guardian_id', $guardianIds)
            ->where('name', $lead->student_name)
            ->latest()
            ->first();
    }
}
