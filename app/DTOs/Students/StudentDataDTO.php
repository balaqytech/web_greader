<?php

namespace App\DTOs\Students;

use App\Enums\Gender;

final readonly class StudentDataDTO
{
    public function __construct(
        public ?int $guardianId,
        public int $branchId,
        public string $name,
        public ?Gender $gender = null,
        public ?string $birthDate = null,
        public ?string $civilNumber = null,
        public ?string $state = null,
        public ?string $governorate = null,
        public ?string $village = null,
        public ?string $houseNumber = null,
        public ?string $parentsSocialStatus = null,

        // Father data
        public ?string $fatherName = null,
        public ?string $fatherPhone = null,
        public ?string $fatherEmail = null,
        public ?string $fatherNationalId = null,
        public ?string $fatherOccupation = null,
        public ?string $fatherWorkAddress = null,
        public ?string $fatherWorkPhone = null,
        public bool $fatherIsGuardian = false,

        // Mother data
        public ?string $motherName = null,
        public ?string $motherPhone = null,
        public ?string $motherEmail = null,
        public ?string $motherNationalId = null,
        public ?string $motherOccupation = null,
        public ?string $motherWorkAddress = null,
        public ?string $motherWorkPhone = null,
        public bool $motherIsGuardian = false,

        // Relative data
        public ?string $relativeName = null,
        public ?string $relativePhone = null,
        public ?string $relativeEmail = null,
        public ?string $relativeRelationship = null,
        public ?string $relativeAddress = null,
        public ?string $relativeWork = null,
        public ?string $relativeWorkPhone = null,
        public ?string $relativeWorkAddress = null,
    ) {}

    /**
     * Convert to a snake_case array suitable for Student::create() or fill().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guardian_id' => $this->guardianId,
            'branch_id' => $this->branchId,
            'name' => $this->name,
            'gender' => $this->gender,
            'birth_date' => $this->birthDate,
            'civil_number' => $this->civilNumber,
            'state' => $this->state,
            'governorate' => $this->governorate,
            'village' => $this->village,
            'house_number' => $this->houseNumber,
            'parents_social_status' => $this->parentsSocialStatus,
            'father_data' => [
                'name' => $this->fatherName,
                'phone' => $this->fatherPhone,
                'email' => $this->fatherEmail,
                'national_id' => $this->fatherNationalId,
                'occupation' => $this->fatherOccupation,
                'work_address' => $this->fatherWorkAddress,
                'work_phone' => $this->fatherWorkPhone,
                'is_guardian' => $this->fatherIsGuardian,
            ],
            'mother_data' => [
                'name' => $this->motherName,
                'phone' => $this->motherPhone,
                'email' => $this->motherEmail,
                'national_id' => $this->motherNationalId,
                'occupation' => $this->motherOccupation,
                'work_address' => $this->motherWorkAddress,
                'work_phone' => $this->motherWorkPhone,
                'is_guardian' => $this->motherIsGuardian,
            ],
            'relative_data' => [
                'name' => $this->relativeName,
                'phone' => $this->relativePhone,
                'email' => $this->relativeEmail,
                'relationship' => $this->relativeRelationship,
                'address' => $this->relativeAddress,
                'work' => $this->relativeWork,
                'work_phone' => $this->relativeWorkPhone,
                'work_address' => $this->relativeWorkAddress,
            ],
        ];
    }

    public static function fromValidated(array $data): self
    {
        return new self(
            guardianId: $data['guardian_id'] ?? null,
            branchId: $data['branch_id'],
            name: $data['name'],
            // gender: isset($data['gender']) ? Gender::tryFrom($data['gender']) : null,
            gender: $data['gender'] ?? null,
            birthDate: $data['birth_date'] ?? null,
            civilNumber: $data['civil_number'] ?? null,
            state: $data['state'] ?? null,
            governorate: $data['governorate'] ?? null,
            village: $data['village'] ?? null,
            houseNumber: $data['house_number'] ?? null,
            parentsSocialStatus: $data['parents_social_status'] ?? null,
            fatherName: $data['father_name'] ?? null,
            fatherPhone: $data['father_phone'] ?? null,
            fatherEmail: $data['father_email'] ?? null,
            fatherNationalId: $data['father_national_id'] ?? null,
            fatherOccupation: $data['father_occupation'] ?? null,
            fatherWorkAddress: $data['father_work_address'] ?? null,
            fatherWorkPhone: $data['father_work_phone'] ?? null,
            fatherIsGuardian: $data['father_is_guardian'] ?? false,
            motherName: $data['mother_name'] ?? null,
            motherPhone: $data['mother_phone'] ?? null,
            motherEmail: $data['mother_email'] ?? null,
            motherNationalId: $data['mother_national_id'] ?? null,
            motherOccupation: $data['mother_occupation'] ?? null,
            motherWorkAddress: $data['mother_work_address'] ?? null,
            motherWorkPhone: $data['mother_work_phone'] ?? null,
            motherIsGuardian: $data['mother_is_guardian'] ?? false,
            relativeName: $data['relative_name'] ?? null,
            relativePhone: $data['relative_phone'] ?? null,
            relativeEmail: $data['relative_email'] ?? null,
            relativeRelationship: $data['relative_relationship'] ?? null,
            relativeAddress: $data['relative_address'] ?? null,
            relativeWork: $data['relative_work'] ?? null,
            relativeWorkPhone: $data['relative_work_phone'] ?? null,
            relativeWorkAddress: $data['relative_work_address'] ?? null,
        );
    }
}
