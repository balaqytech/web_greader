<?php

declare(strict_types=1);

use App\Actions\Applications\UpdateApplicationDataAction;
use App\Actions\Corrections\CompleteCorrectionAction;
use App\Actions\Corrections\RequestCorrectionAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Exceptions\StaleApplicationStateException;
use App\Models\Application;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\CorrectionRequested;

/**
 * Finding 1: the application-edit path must lock and revalidate the persisted editable state, so
 * a stale caller cannot commit an edit after a concurrent request has moved the application out
 * of an editable state.
 */
function editDto(Application $application, array $overrides = []): UpdateApplicationDataDTO
{
    $attributes = $application->fresh()->getAttributes();

    $required = [
        'student_name', 'student_birth_date', 'student_civil_number', 'student_state',
        'student_governorate', 'student_village', 'student_house_number', 'student_parents_social_status',
        'father_name', 'father_phone', 'father_id_number', 'father_occupation',
        'mother_name', 'mother_phone', 'mother_id_number', 'mother_occupation',
        'relative_name', 'relative_phone', 'relative_id_number', 'relative_occupation',
    ];

    foreach ($required as $key) {
        if (empty($attributes[$key])) {
            $attributes[$key] = 'x';
        }
    }

    $attributes['father_is_guardian'] = (bool) ($attributes['father_is_guardian'] ?? false);
    $attributes['mother_is_guardian'] = (bool) ($attributes['mother_is_guardian'] ?? false);
    $attributes['is_transfer_student'] = (bool) ($attributes['is_transfer_student'] ?? false);

    return UpdateApplicationDataDTO::fromValidated(array_merge($attributes, $overrides));
}

it('rejects a stale edit that commits after the correction was completed', function () {
    $this->actingAs(User::factory()->create());
    $application = Application::factory()->awaitingBranchReview()->create();
    app(RequestCorrectionAction::class)->handle($application, auth()->user(), 'fix', ['a']);

    // A staff member opened the edit form while the correction was open.
    $stale = Application::withoutGlobalScope(BranchScope::class)->find($application->id);
    expect($stale->status)->toBeInstanceOf(CorrectionRequested::class);

    // Meanwhile the correction is completed (non-relevant → back to branch review, no open correction).
    app(CompleteCorrectionAction::class)->handle($application->fresh(), auth()->user(), [0]);

    // The stale edit must now be refused: the persisted state is no longer editable.
    expect(fn () => app(UpdateApplicationDataAction::class)->execute($stale, editDto($stale, ['student_name' => 'Stale Overwrite'])))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->student_name)->not->toBe('Stale Overwrite')
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingBranchReview::class);
});

it('rejects a stale edit that commits after a contract was generated', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();
    $stale = Application::withoutGlobalScope(BranchScope::class)->find($application->id);

    // Data completion generates a contract and moves to signature.
    $application->status->transitionTo(AwaitingContractSignature::class);
    $snapshotBefore = $application->fresh()->activeContract->data_snapshot;

    expect(fn () => app(UpdateApplicationDataAction::class)->execute($stale, editDto($stale, ['student_name' => 'After Generation'])))
        ->toThrow(StaleApplicationStateException::class);

    $application->refresh();

    expect($application->student_name)->not->toBe('After Generation')
        ->and($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->activeContract->data_snapshot)->toBe($snapshotBefore);
});

it('applies a normal edit in each valid editable state', function (string $factoryState, bool $withCorrection) {
    $this->actingAs(User::factory()->create());
    $application = Application::factory()->{$factoryState}()->create(['student_name' => 'Original']);

    if ($withCorrection) {
        app(RequestCorrectionAction::class)->handle($application, auth()->user(), 'fix', ['a']);
        $application = $application->fresh();
    }

    $updated = app(UpdateApplicationDataAction::class)->execute($application, editDto($application, ['student_name' => 'Edited Name']));

    expect($updated->student_name)->toBe('Edited Name')
        ->and($application->fresh()->student_name)->toBe('Edited Name');
})->with([
    'registration fee' => ['awaitingRegistrationFee', false],
    'data completion' => ['awaitingApplicationCompletion', false],
    'open correction' => ['awaitingBranchReview', true],
]);

it('rejects an edit in CorrectionRequested with no open correction', function () {
    // Factory correctionRequested has no open correction row.
    $application = Application::factory()->correctionRequested()->create(['student_name' => 'Original']);

    expect(fn () => app(UpdateApplicationDataAction::class)->execute($application, editDto($application, ['student_name' => 'Nope'])))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->student_name)->toBe('Original');
});
