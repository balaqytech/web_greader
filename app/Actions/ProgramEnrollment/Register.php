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
        try {
            DB::transaction(function () use ($data) {
                $parent = $this->findOrCreateParent($data['father']);
                $data['student']['additional_info'] = [
                    'father' => $data['father'],
                    'mother' => $data['mother'],
                    'relative' => $data['relative'],
                ];

                $enrollment = $this->createEnrollment($parent, $data['student'], $data['student']['additional_info']);
            });
            return [
                'success' => true,
                'message' => __('frontend.program_register.success_message'),
            ];
        } catch (\Exception $e) {
            Log::error('Program enrollment registration failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw ValidationException::withMessages([
                'general' => __('frontend.program_register.error_message')
            ]);
        }
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
            $parentData['branch_id'] = 1;
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
    private function createEnrollment(ParentAccount $parent, array $studentData, array $additionalInfo): ProgramEnrollment
    {
        $student = $this->findOrCreateStudent($parent, $studentData);

        return $student->programEnrollments()->create([
            'program_id' => $studentData['program_id'],
            'additional_info' => $additionalInfo,
            'source' => $studentData['source'] ?? EnrollmentSource::WEBSITE->value,
        ]);
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
}
