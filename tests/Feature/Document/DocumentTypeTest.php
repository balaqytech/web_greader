<?php

declare(strict_types=1);

use App\Enums\DocumentType;

it('defines exactly the nine confirmed document types', function () {
    expect(array_map(fn (DocumentType $type): string => $type->value, DocumentType::cases()))
        ->toBe([
            'birth_certificate',
            'student_civil_id',
            'passport',
            'personal_photo',
            'transfer_file',
            'vaccination_card',
            'mother_id',
            'father_id',
            'medical_examination_card',
        ]);
});

it('groups civil id and passport under the student identity requirement group', function () {
    expect(DocumentType::StudentCivilId->getRequirementGroup())->toBe('student_identity')
        ->and(DocumentType::Passport->getRequirementGroup())->toBe('student_identity');
});

it('leaves every other type without a requirement group', function () {
    $grouped = [DocumentType::StudentCivilId, DocumentType::Passport];

    foreach (DocumentType::cases() as $type) {
        if (in_array($type, $grouped, true)) {
            continue;
        }

        expect($type->getRequirementGroup())->toBeNull();
    }
});

it('identifies only the transfer file as transfer-only', function () {
    foreach (DocumentType::cases() as $type) {
        expect($type->isTransferOnly())->toBe($type === DocumentType::TransferFile);
    }
});

it('returns a translated label for every type', function () {
    foreach (DocumentType::cases() as $type) {
        expect($type->getLabel())
            ->toBeString()
            ->not->toBe('')
            ->toBe(__("admin.document.types.{$type->value}"));
    }
});
