<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case BirthCertificate = 'birth_certificate';
    case StudentCivilId = 'student_civil_id';
    case Passport = 'passport';
    case PersonalPhoto = 'personal_photo';
    case TransferFile = 'transfer_file';
    case VaccinationCard = 'vaccination_card';
    case MotherID = 'mother_id';
    case FatherID = 'father_id';
    case MedicalExaminationCard = 'medical_examination_card';

    public function getLabel(): string
    {
        return __("admin.document.types.{$this->value}");
    }

    public function getRequirementGroup(): ?string
    {
        return match ($this) {
            self::StudentCivilId, self::Passport => 'student_identity',
            default => null,
        };
    }

    public function isTransferOnly(): bool
    {
        return $this === self::TransferFile;
    }
}
