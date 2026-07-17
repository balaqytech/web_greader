<?php

declare(strict_types=1);

use App\Actions\Applications\UpdateApplicationDataAction;
use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;

function updateTransfer(Application $application, bool $isTransfer): Application
{
    $data = $application->fresh()->getAttributes();

    // The factory leaves some non-nullable DTO fields empty; backfill them so the DTO can be
    // built. The test only cares about the transfer flag driving resynchronisation.
    $requiredStrings = [
        'student_name', 'student_birth_date', 'student_civil_number', 'student_state',
        'student_governorate', 'student_village', 'student_house_number', 'student_parents_social_status',
        'father_name', 'father_phone', 'father_id_number', 'father_occupation',
        'mother_name', 'mother_phone', 'mother_id_number', 'mother_occupation',
        'relative_name', 'relative_phone', 'relative_id_number', 'relative_occupation',
    ];

    foreach ($requiredStrings as $key) {
        if (empty($data[$key])) {
            $data[$key] = 'x';
        }
    }

    $data['father_is_guardian'] = (bool) ($data['father_is_guardian'] ?? false);
    $data['mother_is_guardian'] = (bool) ($data['mother_is_guardian'] ?? false);
    $data['is_transfer_student'] = $isTransfer;

    return app(UpdateApplicationDataAction::class)->execute($application, UpdateApplicationDataDTO::fromValidated($data));
}

function countDocuments(Application $application): int
{
    return ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->count();
}

it('resynchronises requirements when the transfer flag is turned on during data completion', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => false]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    expect(countDocuments($application))->toBe(8);

    updateTransfer($application, true);

    expect(countDocuments($application))->toBe(9);
});

it('marks the transfer file optional when the flag is turned off during data completion', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => true]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    updateTransfer($application, false);

    $transfer = ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->where('type', 'transfer_file')
        ->first();

    expect($transfer->is_required)->toBeFalse()
        ->and(countDocuments($application))->toBe(9);
});

it('does not create requirements when the flag is unchanged', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => false]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    updateTransfer($application, false);

    expect(countDocuments($application))->toBe(8);
});
