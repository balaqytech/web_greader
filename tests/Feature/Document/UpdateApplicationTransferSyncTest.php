<?php

declare(strict_types=1);

use App\Actions\Applications\UpdateApplicationDataAction;
use App\Actions\Corrections\RequestCorrectionAction;
use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;
use App\Models\User;

function applicationTransferData(Application $application, bool $isTransfer): array
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

    return $data;
}

function updateTransfer(Application $application, bool $isTransfer): Application
{
    return app(UpdateApplicationDataAction::class)->execute(
        $application,
        UpdateApplicationDataDTO::fromValidated(applicationTransferData($application, $isTransfer)),
    );
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

it('resynchronises requirements when the transfer flag is changed through the edit page', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => false]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    $page = app(EditApplication::class);
    $updateThroughPage = function (Application $record, array $data): Application {
        return $this->handleRecordUpdate($record, $data);
    };
    $updated = $updateThroughPage->call(
        $page,
        $application,
        applicationTransferData($application, true),
    );

    expect($updated->is_transfer_student)->toBeTrue()
        ->and(countDocuments($application))->toBe(9);
});

// ── Finding 2: reconciliation is owned by the domain action, including during a correction ──

function correctionApplication(bool $isTransfer): Application
{
    $application = Application::factory()->awaitingBranchReview()->create(['is_transfer_student' => $isTransfer]);
    app(SyncRequiredDocumentsAction::class)->execute($application);
    app(RequestCorrectionAction::class)->handle($application, auth()->user() ?? User::factory()->create(), 'fix', ['a']);

    return $application->fresh();
}

it('resynchronises requirements when the transfer flag is turned on during a correction', function () {
    $this->actingAs(User::factory()->create());
    $application = correctionApplication(false);

    expect(countDocuments($application))->toBe(8);

    updateTransfer($application, true);

    expect(countDocuments($application))->toBe(9);
});

it('marks the transfer file optional when the flag is turned off during a correction', function () {
    $this->actingAs(User::factory()->create());
    $application = correctionApplication(true);

    updateTransfer($application, false);

    $transfer = ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->where('type', 'transfer_file')
        ->first();

    expect($transfer->is_required)->toBeFalse()
        ->and(countDocuments($application))->toBe(9);
});

it('rolls back the whole edit when the requirement resynchronisation fails', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => false]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    // The action resolves SyncRequiredDocumentsAction from the container inline, so a throwing
    // stand-in forces the in-transaction reconciliation to fail.
    app()->bind(SyncRequiredDocumentsAction::class, fn () => new class
    {
        public function execute(Application $application): void
        {
            throw new RuntimeException('forced sync failure');
        }
    });

    expect(fn () => updateTransfer($application, true))->toThrow(RuntimeException::class);

    // The transfer flag change was rolled back with the whole transaction.
    expect($application->fresh()->is_transfer_student)->toBeFalse()
        ->and(countDocuments($application))->toBe(8);
});

it('produces the same result whether reconciliation runs through the domain action or the edit page', function () {
    $viaAction = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => false]);
    app(SyncRequiredDocumentsAction::class)->execute($viaAction);
    updateTransfer($viaAction, true);

    $viaPage = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => false]);
    app(SyncRequiredDocumentsAction::class)->execute($viaPage);
    $page = app(EditApplication::class);
    (function (Application $record, array $data): Application {
        return $this->handleRecordUpdate($record, $data);
    })->call($page, $viaPage, applicationTransferData($viaPage, true));

    expect(countDocuments($viaAction))->toBe(countDocuments($viaPage))
        ->and($viaAction->fresh()->is_transfer_student)->toBe($viaPage->fresh()->is_transfer_student);
});
