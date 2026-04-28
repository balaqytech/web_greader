<?php

namespace App\DTOs\Application;

use App\Enums\Gender;

final readonly class UpdateApplicationDataDTO
{
    public function __construct(
        // Student data
        public string $studentName,
        public ?Gender $studentGender = null,
        public ?string $studentBirthDate = null,
        public ?string $studentCivilNumber = null,
        public ?string $studentState = null,
        public ?string $studentGovernorate = null,
        public ?string $studentVillage = null,
        public ?string $studentHouseNumber = null,
        public ?string $studentParentsSocialStatus = null,

        // Father data
        public ?string $fatherName = null,
        public ?string $fatherPhone = null,
        public ?string $fatherEmail = null,
        public ?string $fatherIdNumber = null,
        public ?string $fatherOccupation = null,
        public ?string $fatherWorkAddress = null,
        public ?string $fatherWorkPhone = null,
        public bool $fatherIsGuardian = false,

        // Mother data
        public ?string $motherName = null,
        public ?string $motherPhone = null,
        public ?string $motherEmail = null,
        public ?string $motherIdNumber = null,
        public ?string $motherOccupation = null,
        public ?string $motherWorkAddress = null,
        public ?string $motherWorkPhone = null,
        public bool $motherIsGuardian = false,

        // Relative data
        public ?string $relativeName = null,
        public ?string $relativePhone = null,
        public ?string $relativeEmail = null,
        public ?string $relativeIdNumber = null,
        public ?string $relativeOccupation = null,
        public ?string $relativeWorkAddress = null,
        public ?string $relativeWorkPhone = null,
    ) {}

    /**
     * Convert to a snake_case array suitable for Application::fill().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_name' => $this->studentName,
            'student_gender' => $this->studentGender,
            'student_birth_date' => $this->studentBirthDate,
            'student_civil_number' => $this->studentCivilNumber,
            'student_state' => $this->studentState,
            'student_governorate' => $this->studentGovernorate,
            'student_village' => $this->studentVillage,
            'student_house_number' => $this->studentHouseNumber,
            'student_parents_social_status' => $this->studentParentsSocialStatus,
            'father_name' => $this->fatherName,
            'father_phone' => $this->fatherPhone,
            'father_email' => $this->fatherEmail,
            'father_id_number' => $this->fatherIdNumber,
            'father_occupation' => $this->fatherOccupation,
            'father_work_address' => $this->fatherWorkAddress,
            'father_work_phone' => $this->fatherWorkPhone,
            'father_is_guardian' => $this->fatherIsGuardian,
            'mother_name' => $this->motherName,
            'mother_phone' => $this->motherPhone,
            'mother_email' => $this->motherEmail,
            'mother_id_number' => $this->motherIdNumber,
            'mother_occupation' => $this->motherOccupation,
            'mother_work_address' => $this->motherWorkAddress,
            'mother_work_phone' => $this->motherWorkPhone,
            'mother_is_guardian' => $this->motherIsGuardian,
            'relative_name' => $this->relativeName,
            'relative_phone' => $this->relativePhone,
            'relative_email' => $this->relativeEmail,
            'relative_id_number' => $this->relativeIdNumber,
            'relative_occupation' => $this->relativeOccupation,
            'relative_work_address' => $this->relativeWorkAddress,
            'relative_work_phone' => $this->relativeWorkPhone,
        ];
    }

    public static function fromValidated(array $data): self
    {
        return new self(
            studentName: $data['student_name'],
            studentGender: $data['student_gender'],
            studentBirthDate: $data['student_birth_date'] ?? null,
            studentCivilNumber: $data['student_civil_number'] ?? null,
            studentState: $data['student_state'] ?? null,
            studentGovernorate: $data['student_governorate'] ?? null,
            studentVillage: $data['student_village'] ?? null,
            studentHouseNumber: $data['student_house_number'] ?? null,
            studentParentsSocialStatus: $data['student_parents_social_status'] ?? null,
            fatherName: $data['father_name'] ?? null,
            fatherPhone: $data['father_phone'] ?? null,
            fatherEmail: $data['father_email'] ?? null,
            fatherIdNumber: $data['father_id_number'] ?? null,
            fatherOccupation: $data['father_occupation'] ?? null,
            fatherWorkAddress: $data['father_work_address'] ?? null,
            fatherWorkPhone: $data['father_work_phone'] ?? null,
            fatherIsGuardian: $data['father_is_guardian'] ?? false,
            motherName: $data['mother_name'] ?? null,
            motherPhone: $data['mother_phone'] ?? null,
            motherEmail: $data['mother_email'] ?? null,
            motherIdNumber: $data['mother_id_number'] ?? null,
            motherOccupation: $data['mother_occupation'] ?? null,
            motherWorkAddress: $data['mother_work_address'] ?? null,
            motherWorkPhone: $data['mother_work_phone'] ?? null,
            motherIsGuardian: $data['mother_is_guardian'] ?? false,
            relativeName: $data['relative_name'] ?? null,
            relativePhone: $data['relative_phone'] ?? null,
            relativeEmail: $data['relative_email'] ?? null,
            relativeIdNumber: $data['relative_id_number'] ?? null,
            relativeOccupation: $data['relative_occupation'] ?? null,
            relativeWorkAddress: $data['relative_work_address'] ?? null,
            relativeWorkPhone: $data['relative_work_phone'] ?? null,
        );
    }
}
