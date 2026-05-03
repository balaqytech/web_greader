<?php

namespace App\DTOs\Application;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
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
        ], fn ($value) => filled($value));
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

    /**
     * Build a DTO from Filament form data (wizard submit).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromFormData(array $data): self
    {
        $studentData = $data['applicationStudent'] ?? [];
        $contactData = $data['contacts'] ?? [];

        // Convert each contact to a clean array with proper keys
        $contacts = array_map(function ($contact) {
            $relationshipWithGuardian = $contact['relationship_with_guardian'] ?? null;
            $isGuardian = $relationshipWithGuardian !== null;

            // Convert enum values from string to enum instance if necessary
            $relationshipWithGuardianEnum = $relationshipWithGuardian instanceof GuardianRelationship
                ? $relationshipWithGuardian
                : GuardianRelationship::tryFrom($relationshipWithGuardian);

            return array_filter([
                'relationship' => $relationshipWithGuardianEnum,
                'name' => $contact['name'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'email' => $contact['email'] ?? null,
                'id_number' => $contact['id_number'] ?? null,
                'occupation' => $contact['occupation'] ?? null,
                'work_address' => $contact['work_address'] ?? null,
                'work_phone' => $contact['work_phone'] ?? null,
                'is_guardian' => $isGuardian,
            ], fn ($value) => filled($value));
        }, $contactData);

        return new self(
            programId: $data['program_id'],
            branchId: $data['branch_id'],
            seasonId: $data['season_id'],
            studentName: $studentData['name'] ?? null,
            studentGender: $studentData['gender'] instanceof Gender ? $studentData['gender'] : Gender::tryFrom($studentData['gender']),
            studentBirthDate: $studentData['birth_date'] ?? null,
            studentCivilNumber: $studentData['civil_number'] ?? null,
            studentState: $studentData['state'] ?? null,
            studentGovernorate: $studentData['governorate'] ?? null,
            studentVillage: $studentData['village'] ?? null,
            studentHouseNumber: $studentData['house_number'] ?? null,
            studentParentsSocialStatus: $studentData['parents_social_status'] ?? null,
            contacts: array_values($data['contacts'] ?? []),
        );
    }
}
