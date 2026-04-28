<?php

namespace App\DTOs\Application;

use App\Enums\Gender;
use App\Enums\Source;
use App\Models\Lead;

class CreateApplicationDTO
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public int $programId,
        public ?int $leadId,
        public int $branchId,
        public int $seasonId,
        public Source $source,
        public ?int $affiliateId,

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

    public function toArray(): array
    {
        return [
            'program_id' => $this->programId,
            'lead_id' => $this->leadId,
            'branch_id' => $this->branchId,
            'season_id' => $this->seasonId,
            'source' => $this->source,
            'affiliate_id' => $this->affiliateId,
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

    public static function fromLead(Lead $lead): self
    {
        return new self(
            programId: $lead->program_id,
            leadId: $lead->id,
            branchId: $lead->branch_id,
            seasonId: $lead->season_id,
            source: $lead->source,
            affiliateId: $lead->affiliate_id,
            studentName: $lead->student_name,
            fatherName: $lead->guardian_name,
            fatherPhone: $lead->whatsapp,
        );
    }
}
