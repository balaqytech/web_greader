<?php

namespace App\DTOs\Application;

use App\Enums\ContactType;
use App\Enums\Gender;
use App\Models\Lead;

class CreateApplicationDTO
{
    public function __construct(
        public int $programId,
        public int $branchId,
        public int $seasonId,
        public ?int $leadId = null,
        public ?int $affiliateId = null,

        // Student data
        public ?string $studentName = null,
        public ?Gender $studentGender = null,
        public ?string $studentBirthDate = null,
        public ?string $studentCivilNumber = null,
        public ?string $studentState = null,
        public ?string $studentGovernorate = null,
        public ?string $studentVillage = null,
        public ?string $studentHouseNumber = null,
        public ?string $studentParentsSocialStatus = null,

        // Contacts (replaces the old flat father/mother/relative fields)
        public array $contacts = [],
    ) {}

    /**
     * Get only the Application table fields.
     *
     * @return array<string, mixed>
     */
    public function toApplicationArray(): array
    {
        return [
            'program_id' => $this->programId,
            'lead_id' => $this->leadId,
            'branch_id' => $this->branchId,
            'season_id' => $this->seasonId,
            'affiliate_id' => $this->affiliateId,
        ];
    }

    /**
     * Get the student data for ApplicationStudent.
     *
     * @return array<string, mixed>
     */
    public function toStudentArray(): array
    {
        return array_filter([
            'name' => $this->studentName,
            'gender' => $this->studentGender,
            'birth_date' => $this->studentBirthDate,
            'civil_number' => $this->studentCivilNumber,
            'state' => $this->studentState,
            'governorate' => $this->studentGovernorate,
            'village' => $this->studentVillage,
            'house_number' => $this->studentHouseNumber,
            'parents_social_status' => $this->studentParentsSocialStatus,
        ], fn($value) => filled($value));
    }

    /**
     * Get the contacts data for ApplicationContact records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toContactsArray(): array
    {
        return $this->contacts;
    }

    public static function fromLead(Lead $lead): self
    {
        // Build a father contact from the lead's guardian data
        $contacts = [];
        if (filled($lead->guardian_name)) {
            $contacts[] = [
                'type' => ContactType::Father,
                'name' => $lead->guardian_name,
                'phone' => $lead->whatsapp,
                'is_guardian' => true,
            ];
        }

        return new self(
            programId: $lead->program_id,
            branchId: $lead->branch_id,
            seasonId: $lead->season_id,
            leadId: $lead->id,
            affiliateId: $lead->affiliate_id,
            studentName: $lead->student_name,
            contacts: $contacts,
        );
    }
}
