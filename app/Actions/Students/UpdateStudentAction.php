<?php

namespace App\Actions\Students;

use App\DTOs\Students\StudentDataDTO;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

final class UpdateStudentAction
{
    /**
     * Update an existing student.
     */
    public function execute(Student $student, StudentDataDTO $dto): Student
    {
        $data = $dto->toArray();

        DB::transaction(function () use ($data, $student) {
            $student->update($data);
        });

        return $student->fresh();
    }
}
