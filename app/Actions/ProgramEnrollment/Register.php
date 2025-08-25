<?php

namespace App\Actions\ProgramEnrollment;

use App\Models\Student;
use App\Models\ParentAccount;
use App\Enums\EnrollmentSource;
use App\Models\ProgramEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Register
{
    /**
     * Execute the program enrollment registration
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function execute(array $data): array
    {
        // try {
        DB::transaction(function () use ($data) {
            $parent = $this->findOrCreateParent($data['parent']);
            $enrollments = $this->createEnrollments($parent, $data['students'], $data['additional_info'] ?? []);
        });
        return [
            'success' => true,
            'message' => __('frontend.program_register.success_message'),
        ];
        // } catch (\Exception $e) {
        //     Log::error('Program enrollment registration failed', [
        //         'error' => $e->getMessage(),
        //         'data' => $data
        //     ]);

        //     throw ValidationException::withMessages([
        //         'general' => __('frontend.program_register.error_message')
        //     ]);
        // }
    }

    /**
     * Find existing parent or create new one
     *
     * @param array $parentData
     * @return ParentAccount
     */
    private function findOrCreateParent(array $parentData): ParentAccount
    {
        $parent = ParentAccount::where('email', $parentData['email'])
            ->orWhere('phone', $parentData['phone'])
            ->first();

        if (!$parent) {
            $parentData['password'] = Hash::make('123456');
            $parentData['is_active'] = true;
            $parent = ParentAccount::create($parentData);
        }

        return $parent;
    }

    /**
     * Create enrollments for students
     *
     * @param ParentAccount $parent
     * @param array $studentsData
     * @param array $additionalInfo
     * @return \Illuminate\Support\Collection
     */
    private function createEnrollments(ParentAccount $parent, array $studentsData, array $additionalInfo): \Illuminate\Support\Collection
    {
        $enrollments = collect();

        foreach ($studentsData as $studentData) {
            $student = $this->findOrCreateStudent($parent, $studentData);
            $enrollment = $this->createEnrollment($student, $studentData, $additionalInfo);
            $enrollments->push($enrollment);
        }

        return $enrollments;
    }

    /**
     * Find existing student or create new one
     *
     * @param ParentAccount $parent
     * @param array $studentData
     * @return Student
     */
    private function findOrCreateStudent(ParentAccount $parent, array $studentData): Student
    {
        return $parent->students()->updateOrCreate(
            [
                'name' => $studentData['name'],
            ],
            $studentData
        );
    }

    /**
     * Create program enrollment
     *
     * @param Student $student
     * @param array $studentData
     * @param array $additionalInfo
     * @return ProgramEnrollment
     */
    private function createEnrollment(Student $student, array $studentData, array $additionalInfo): ProgramEnrollment
    {
        return $student->programEnrollments()->create([
            'program_id' => $studentData['program_id'],
            'additional_info' => $additionalInfo,
            'already_registered' => $studentData['already_registered'] ?? false,
            'has_siblings' => $studentData['has_siblings'] ?? false,
            'source' => $studentData['source'] ?? EnrollmentSource::WEBSITE->value,
        ]);
    }
}
