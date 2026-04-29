<?php

namespace App\Actions\Students;

use App\DTOs\Students\StudentDataDTO;
use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateStudentAction
{
    /**
     * Create a new student.
     */
    public function execute(StudentDataDTO $dto): Student
    {
        $data = $dto->toArray();

        if ($dto->fatherIsGuardian && $dto->motherIsGuardian) {
            throw ValidationException::withMessages([
                'father_is_guardian' => __('admin.student.only_one_guardian'),
            ]);
        }

        return DB::transaction(function () use ($dto, $data) {
            if (empty($data['guardian_id'])) {
                if (! $dto->fatherIsGuardian && ! $dto->motherIsGuardian) {
                    throw ValidationException::withMessages([
                        'guardian_id' => __('admin.student.guardian_required'),
                    ]);
                }
                if ($dto->fatherIsGuardian) {
                    $guardian = Guardian::create([
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
                    $guardian = Guardian::create([
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
                }
            }
            return Student::create($data);
        });
    }
}
