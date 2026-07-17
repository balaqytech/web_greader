<?php

namespace App\DTOs\Application;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;

final readonly class UpdateApplicationDataDTO
{
    public function __construct(
        // Student data
        public string $student_name,
        public Gender $student_gender,
        public string $student_birth_date,
        public string $student_civil_number,
        public string $student_state,
        public string $student_governorate,
        public string $student_village,
        public string $student_house_number,
        public string $student_parents_social_status,
        public GuardianRelationship $relationship_with_guardian,

        // Father data
        public string $father_name,
        public string $father_phone,
        public ?string $father_email,
        public string $father_id_number,
        public string $father_occupation,
        public ?string $father_work_address,
        public ?string $father_work_phone,
        public bool $father_is_guardian,

        // Mother data
        public string $mother_name,
        public string $mother_phone,
        public ?string $mother_email,
        public string $mother_id_number,
        public string $mother_occupation,
        public ?string $mother_work_address,
        public ?string $mother_work_phone,
        public bool $mother_is_guardian,

        // Relative data
        public string $relative_name,
        public string $relative_phone,
        public ?string $relative_email,
        public string $relative_id_number,
        public string $relative_occupation,
        public ?string $relative_work_address = null,
        public ?string $relative_work_phone = null,
        public bool $is_transfer_student = false,
    ) {}

    /**
     * Convert to a snake_case array suitable for Application::fill().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_name' => $this->student_name,
            'student_gender' => $this->student_gender,
            'student_birth_date' => $this->student_birth_date,
            'student_civil_number' => $this->student_civil_number,
            'student_state' => $this->student_state,
            'student_governorate' => $this->student_governorate,
            'student_village' => $this->student_village,
            'student_house_number' => $this->student_house_number,
            'student_parents_social_status' => $this->student_parents_social_status,
            'father_name' => $this->father_name,
            'father_phone' => $this->father_phone,
            'father_email' => $this->father_email,
            'father_id_number' => $this->father_id_number,
            'father_occupation' => $this->father_occupation,
            'father_work_address' => $this->father_work_address,
            'father_work_phone' => $this->father_work_phone,
            'father_is_guardian' => $this->father_is_guardian,
            'mother_name' => $this->mother_name,
            'mother_phone' => $this->mother_phone,
            'mother_email' => $this->mother_email,
            'mother_id_number' => $this->mother_id_number,
            'mother_occupation' => $this->mother_occupation,
            'mother_work_address' => $this->mother_work_address,
            'mother_work_phone' => $this->mother_work_phone,
            'mother_is_guardian' => $this->mother_is_guardian,
            'relative_name' => $this->relative_name,
            'relative_phone' => $this->relative_phone,
            'relative_email' => $this->relative_email,
            'relative_id_number' => $this->relative_id_number,
            'relative_occupation' => $this->relative_occupation,
            'relative_work_address' => $this->relative_work_address,
            'relative_work_phone' => $this->relative_work_phone,
            'is_transfer_student' => $this->is_transfer_student,
        ];
    }

    public static function fromValidated(array $data): self
    {
        return new self(
            student_name: $data['student_name'],
            student_gender: $data['student_gender'] instanceof Gender ? $data['student_gender'] : Gender::tryFrom($data['student_gender']),
            student_birth_date: $data['student_birth_date'] ?? null,
            student_civil_number: $data['student_civil_number'] ?? null,
            student_state: $data['student_state'] ?? null,
            student_governorate: $data['student_governorate'] ?? null,
            student_village: $data['student_village'] ?? null,
            student_house_number: $data['student_house_number'] ?? null,
            student_parents_social_status: $data['student_parents_social_status'] ?? null,
            relationship_with_guardian: $data['relationship_with_guardian'] instanceof GuardianRelationship ? $data['relationship_with_guardian'] : GuardianRelationship::tryFrom($data['relationship_with_guardian']) ?? null,
            father_name: $data['father_name'] ?? null,
            father_phone: $data['father_phone'] ?? null,
            father_email: $data['father_email'] ?? null,
            father_id_number: $data['father_id_number'] ?? null,
            father_occupation: $data['father_occupation'] ?? null,
            father_work_address: $data['father_work_address'] ?? null,
            father_work_phone: $data['father_work_phone'] ?? null,
            father_is_guardian: $data['father_is_guardian'] ?? false,
            mother_name: $data['mother_name'] ?? null,
            mother_phone: $data['mother_phone'] ?? null,
            mother_email: $data['mother_email'] ?? null,
            mother_id_number: $data['mother_id_number'] ?? null,
            mother_occupation: $data['mother_occupation'] ?? null,
            mother_work_address: $data['mother_work_address'] ?? null,
            mother_work_phone: $data['mother_work_phone'] ?? null,
            mother_is_guardian: $data['mother_is_guardian'] ?? false,
            relative_name: $data['relative_name'] ?? null,
            relative_phone: $data['relative_phone'] ?? null,
            relative_email: $data['relative_email'] ?? null,
            relative_id_number: $data['relative_id_number'] ?? null,
            relative_occupation: $data['relative_occupation'] ?? null,
            relative_work_address: $data['relative_work_address'] ?? null,
            relative_work_phone: $data['relative_work_phone'] ?? null,
            is_transfer_student: $data['is_transfer_student'] ?? false,
        );
    }
}
