<?php

namespace App\DTOs\Application;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Source;
use App\Models\Lead;

class CreateApplicationDTO
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public int $program_id,
        public int $lead_id,
        public int $branch_id,
        public int $season_id,
        public Source $source,
        public ?int $affiliate_id,

        // Student data
        public string $student_name,
        public ?Gender $student_gender = null,
        public ?string $student_birth_date = null,
        public ?string $student_civil_number = null,
        public ?string $student_state = null,
        public ?string $student_governorate = null,
        public ?string $student_village = null,
        public ?string $student_house_number = null,
        public ?string $student_parents_social_status = null,
        public ?GuardianRelationship $relationship_with_guardian = null,

        // Father data
        public ?string $father_name = null,
        public ?string $father_phone = null,
        public ?string $father_email = null,
        public ?string $father_id_number = null,
        public ?string $father_occupation = null,
        public ?string $father_work_address = null,
        public ?string $father_work_phone = null,
        public bool $father_is_guardian = false,

        // Mother data
        public ?string $mother_name = null,
        public ?string $mother_phone = null,
        public ?string $mother_email = null,
        public ?string $mother_id_number = null,
        public ?string $mother_occupation = null,
        public ?string $mother_work_address = null,
        public ?string $mother_work_phone = null,
        public bool $mother_is_guardian = false,

        // Relative data
        public ?string $relative_name = null,
        public ?string $relative_phone = null,
        public ?string $relative_email = null,
        public ?string $relative_id_number = null,
        public ?string $relative_occupation = null,
        public ?string $relative_work_address = null,
        public ?string $relative_work_phone = null,
        public bool $is_transfer_student = false,
    ) {}

    public function toArray(): array
    {
        return [
            'program_id' => $this->program_id,
            'lead_id' => $this->lead_id,
            'branch_id' => $this->branch_id,
            'season_id' => $this->season_id,
            'source' => $this->source,
            'affiliate_id' => $this->affiliate_id,
            'student_name' => $this->student_name,
            'student_gender' => $this->student_gender,
            'student_birth_date' => $this->student_birth_date,
            'student_civil_number' => $this->student_civil_number,
            'student_state' => $this->student_state,
            'student_governorate' => $this->student_governorate,
            'student_village' => $this->student_village,
            'student_house_number' => $this->student_house_number,
            'student_parents_social_status' => $this->student_parents_social_status,
            'relationship_with_guardian' => $this->relationship_with_guardian,
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

    public static function fromLead(Lead $lead): self
    {
        return new self(
            program_id: $lead->program_id,
            lead_id: $lead->id,
            branch_id: $lead->branch_id,
            season_id: $lead->season_id,
            source: $lead->source,
            affiliate_id: $lead->affiliate_id,
            student_name: $lead->student_name,
            father_name: $lead->guardian_name,
            father_phone: $lead->whatsapp,
            mother_phone: $lead->mother_phone,
        );
    }

    /**
     * $leadId is a required, explicit argument (not read from $data) so no caller can
     * construct an application DTO with a null lead_id — every application originates from
     * a lead, and the caller must already have created or resolved one.
     */
    public static function fromFormData(array $data, int $leadId): self
    {
        return new self(
            program_id: $data['program_id'],
            lead_id: $leadId,
            branch_id: $data['branch_id'],
            season_id: $data['season_id'],
            source: $data['source'],
            affiliate_id: $data['affiliate_id'] ?? null,
            student_name: $data['student_name'],
            student_gender: $data['student_gender'],
            student_birth_date: $data['student_birth_date'],
            student_civil_number: $data['student_civil_number'],
            student_state: $data['student_state'],
            student_governorate: $data['student_governorate'],
            student_village: $data['student_village'],
            student_house_number: $data['student_house_number'],
            student_parents_social_status: $data['student_parents_social_status'],
            relationship_with_guardian: $data['relationship_with_guardian'],
            father_name: $data['father_name'],
            father_phone: $data['father_phone'],
            father_email: $data['father_email'] ?? null,
            father_id_number: $data['father_id_number'],
            father_occupation: $data['father_occupation'],
            father_work_address: $data['father_work_address'] ?? null,
            father_work_phone: $data['father_work_phone'] ?? null,
            father_is_guardian: $data['father_is_guardian'],
            mother_name: $data['mother_name'],
            mother_phone: $data['mother_phone'],
            mother_email: $data['mother_email'] ?? null,
            mother_id_number: $data['mother_id_number'],
            mother_occupation: $data['mother_occupation'],
            mother_work_address: $data['mother_work_address'] ?? null,
            mother_work_phone: $data['mother_work_phone'] ?? null,
            mother_is_guardian: $data['mother_is_guardian'],
            relative_name: $data['relative_name'],
            relative_phone: $data['relative_phone'],
            relative_email: $data['relative_email'] ?? null,
            relative_id_number: $data['relative_id_number'],
            relative_occupation: $data['relative_occupation'],
            relative_work_address: $data['relative_work_address'] ?? null,
            relative_work_phone: $data['relative_work_phone'] ?? null,
            is_transfer_student: $data['is_transfer_student'] ?? false,
        );
    }
}
