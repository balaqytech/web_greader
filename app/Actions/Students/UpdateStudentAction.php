<?php

namespace App\Actions\Students;

use App\DTOs\Students\StudentDataDTO;
use App\Enums\GuardianRelationship;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateStudentAction
{
    /**
     * Update an existing student.
     */
    public function execute(Student $student, StudentDataDTO $dto): Student
    {
        $data = $dto->toArray();

        if ($dto->fatherIsGuardian && $dto->motherIsGuardian) {
            throw ValidationException::withMessages([
                'father_is_guardian' => __('admin.student.only_one_guardian'),
                'mother_is_guardian' => __('admin.student.only_one_guardian'),
            ]);
        }

        $guardian = $student->guardian;

        DB::transaction(function () use ($guardian, $data, $dto, $student) {
            if ($dto->fatherIsGuardian) {
                $guardian->update([
                    'name' => $dto->fatherName,
                    'phone' => $dto->fatherPhone,
                    'email' => $dto->fatherEmail,
                    'id_number' => $dto->fatherNationalId,
                    'occupation' => $dto->fatherOccupation,
                    'work_address' => $dto->fatherWorkAddress,
                    'work_phone' => $dto->fatherWorkPhone,
                    'relationship' => GuardianRelationship::Father,
                ]);
                $data['guardian_id'] = $guardian->id;
            } elseif ($dto->motherIsGuardian) {
                $guardian->update([
                    'name' => $dto->motherName,
                    'phone' => $dto->motherPhone,
                    'email' => $dto->motherEmail,
                    'id_number' => $dto->motherNationalId,
                    'occupation' => $dto->motherOccupation,
                    'work_address' => $dto->motherWorkAddress,
                    'work_phone' => $dto->motherWorkPhone,
                    'relationship' => GuardianRelationship::Mother,
                ]);
                $data['guardian_id'] = $guardian->id;
            } elseif (empty($data['guardian_id'])) {
                throw ValidationException::withMessages([
                    'guardian_id' => __('admin.student.guardian_required'),
                ]);
            }

            $student->update($data);
        });

        return $student->fresh();
    }
}
