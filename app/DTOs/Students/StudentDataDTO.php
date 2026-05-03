<?php

namespace App\DTOs\Students;

use App\Enums\Gender;

final readonly class StudentDataDTO
{
    public function __construct(
        public int $branchId,
        public string $name,
        public Gender|string $gender,
        public ?string $birthDate = null,
        public ?string $civilNumber = null,
        public ?string $state = null,
        public ?string $governorate = null,
        public ?string $village = null,
        public ?string $houseNumber = null,
        public ?string $parentsSocialStatus = null,
    ) {}

    /**
     * Convert to a snake_case array suitable for Student::create() or fill().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'branch_id' => $this->branchId,
            'name' => $this->name,
            'gender' => $this->gender instanceof Gender ? $this->gender : Gender::tryFrom($this->gender),
            'birth_date' => $this->birthDate,
            'civil_number' => $this->civilNumber,
            'state' => $this->state,
            'governorate' => $this->governorate,
            'village' => $this->village,
            'house_number' => $this->houseNumber,
            'parents_social_status' => $this->parentsSocialStatus,
        ];
    }

    public static function fromValidated(array $data): self
    {
        return new self(
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
        );
    }
}
