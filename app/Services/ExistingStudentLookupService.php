<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Lead;
use App\Models\Student;
use App\Support\LeadIdentityNormalizer;

final class ExistingStudentLookupService
{
    public function __construct(
        private LeadIdentityNormalizer $normalizer,
    ) {}

    /**
     * Find an existing student matching a lead's guardian phone + student name.
     */
    public function findExistingStudent(Lead $lead): ?Student
    {
        $guardianIds = Guardian::where('phone', $lead->whatsapp)->pluck('id');

        if ($guardianIds->isEmpty()) {
            return null;
        }

        $normalizedName = $lead->student_name_normalized
            ?: $this->normalizer->normalizeName($lead->student_name);

        return Student::whereIn('guardian_id', $guardianIds)
            ->get()
            ->first(fn (Student $student) => $this->normalizer->normalizeName($student->name) === $normalizedName);
    }
}
