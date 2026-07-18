<?php

declare(strict_types=1);

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Corrections\CompleteCorrectionAction;
use App\Actions\Corrections\RequestCorrectionAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Exceptions\CorrectionException;
use App\Exceptions\StaleApplicationStateException;
use App\Models\Application;
use App\Models\Branch;
use App\Models\User;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\CorrectionRequested;
use App\States\Contracts\Signed;
use App\States\Contracts\Superseded;
use Database\Seeders\ShieldPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewApplication(array $overrides = []): Application
{
    return Application::factory()->awaitingBranchReview()->create($overrides);
}

function openCorrectionOn(Application $application, array $items = ['Fix the civil number'], string $reason = 'Please correct'): Application
{
    return app(RequestCorrectionAction::class)->handle($application, $reason, $items);
}

// ── Request ─────────────────────────────────────────────────────────────────

it('opens a correction with reason, checklist, data_before and an activity row', function () {
    $this->actingAs(User::factory()->create());
    $application = reviewApplication();

    openCorrectionOn($application, ['Fix civil number', 'Correct guardian name'], 'Two issues');
    $application->refresh();

    $correction = $application->openCorrection;

    expect($application->status)->toBeInstanceOf(CorrectionRequested::class)
        ->and($correction)->not->toBeNull()
        ->and($correction->reason)->toBe('Two issues')
        ->and($correction->checklist)->toHaveCount(2)
        ->and($correction->checklist[0]['done'])->toBeFalse()
        ->and($correction->data_before['minimum'])->not->toBeEmpty()
        ->and($correction->requested_by)->not->toBeNull()
        ->and($application->activities()->where('to_state', CorrectionRequested::getMorphClass())->exists())->toBeTrue();
});

it('requires a nonblank reason and at least one distinct nonblank item', function () {
    $application = reviewApplication();

    expect(fn () => app(RequestCorrectionAction::class)->handle($application, '   ', ['x']))
        ->toThrow(CorrectionException::class);

    expect(fn () => app(RequestCorrectionAction::class)->handle($application->fresh(), 'reason', ['', '  ']))
        ->toThrow(CorrectionException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingBranchReview::class);
});

it('refuses a correction when the active contract is not signed', function () {
    $application = reviewApplication();
    // Strip the signed artifact so the guard fails.
    $application->activeContract->update(['signed_at' => null, 'file_path' => null]);

    expect(fn () => openCorrectionOn($application))->toThrow(ApplicationIncompleteException::class);
});

it('prevents a second open correction on the same application', function () {
    $application = reviewApplication();
    openCorrectionOn($application);

    // The application is now in CorrectionRequested; a second request cannot even start (the
    // state machine has no CorrectionRequested -> CorrectionRequested edge), and the
    // transition's own no-open-correction guard backs that up under concurrency.
    expect(fn () => openCorrectionOn($application->fresh()))->toThrow(Exception::class);

    expect($application->fresh()->corrections()->count())->toBe(1);
});

// ── Completion: non-contract-relevant returns to review ─────────────────────

it('returns to branch review when nothing contract-relevant changed', function () {
    $this->actingAs(User::factory()->create());
    $application = reviewApplication();
    openCorrectionOn($application);

    // Change a non-printed, non-minimum field only.
    $application->update(['father_phone' => '900000999']);

    $result = app(CompleteCorrectionAction::class)->handle($application->fresh(), [0]);

    $correction = $result->corrections()->first();

    expect($result->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($correction->is_contract_relevant)->toBeFalse()
        ->and($correction->completed_at)->not->toBeNull()
        ->and($correction->completed_by)->not->toBeNull()
        ->and($result->activeContract->status)->toBeInstanceOf(Signed::class);
});

// ── Completion: contract-relevant regenerates + re-signs ────────────────────

it('supersedes the signed contract and requires re-signature when a minimum field changes', function () {
    $this->actingAs(User::factory()->create());
    $application = reviewApplication();
    $signedContract = $application->activeContract;
    openCorrectionOn($application);

    $application->update(['student_name' => 'Corrected Student Name']);

    $result = app(CompleteCorrectionAction::class)->handle($application->fresh(), [0]);

    $signedContract->refresh();
    $correction = $result->corrections()->first();

    expect($result->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($correction->is_contract_relevant)->toBeTrue()
        ->and($signedContract->status)->toBeInstanceOf(Superseded::class)
        ->and($signedContract->superseded_by_contract_id)->toBe($result->activeContract->id)
        ->and($result->activeContract->version)->toBe(2)
        ->and($result->activeContract->status->isActive())->toBeTrue()
        ->and($result->activeContract->data_snapshot['minimum']['student_name'])->toBe('Corrected Student Name');
});

it('treats a template/body change as contract-relevant even with no data change', function () {
    $this->actingAs(User::factory()->create());
    $application = reviewApplication();
    openCorrectionOn($application);

    // Rewrite the contract template itself → template_hash + body differ.
    $application->program->update(['contract' => 'Brand new terms $student_name$']);

    $result = app(CompleteCorrectionAction::class)->handle($application->fresh(), [0]);

    expect($result->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($result->corrections()->first()->is_contract_relevant)->toBeTrue()
        ->and($result->activeContract->version)->toBe(2);
});

it('classifies every confirmed-minimum field change as contract-relevant', function (string $column, string $value) {
    $this->actingAs(User::factory()->create());
    $application = reviewApplication();
    openCorrectionOn($application);

    $application->update([$column => $value]);

    $result = app(CompleteCorrectionAction::class)->handle($application->fresh(), [0]);

    expect($result->status)->toBeInstanceOf(AwaitingContractSignature::class);
})->with([
    'student name' => ['student_name', 'Different Name'],
    'student civil number' => ['student_civil_number', '99887766'],
    'guardian name (father is guardian)' => ['father_name', 'Different Father'],
    'guardian id number' => ['father_id_number', '55554444'],
]);

// ── Checklist / stale / rollback ────────────────────────────────────────────

it('requires every checklist item complete before closing', function () {
    $application = reviewApplication();
    openCorrectionOn($application, ['Item one', 'Item two']);

    // Only one of two items checked.
    expect(fn () => app(CompleteCorrectionAction::class)->handle($application->fresh(), [0]))
        ->toThrow(CorrectionException::class);

    expect($application->fresh()->status)->toBeInstanceOf(CorrectionRequested::class);
});

it('rejects a completion when there is no open correction', function () {
    $application = reviewApplication();

    expect(fn () => app(CompleteCorrectionAction::class)->handle($application, [0]))
        ->toThrow(StaleApplicationStateException::class);
});

it('rolls back the whole completion when a mid-transaction step fails', function () {
    $this->actingAs(User::factory()->create());
    $application = reviewApplication();
    openCorrectionOn($application);
    $application->update(['student_name' => 'Changed Name']);

    app()->bind(RecordApplicationActivityAction::class, fn () => new class
    {
        public function handle($application, $from, $to, $notes = null)
        {
            throw new RuntimeException('forced failure');
        }
    });

    expect(fn () => app(CompleteCorrectionAction::class)->handle($application->fresh(), [0]))
        ->toThrow(RuntimeException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(CorrectionRequested::class)
        ->and($application->openCorrection)->not->toBeNull()
        ->and($application->openCorrection->completed_at)->toBeNull()
        ->and($application->contracts()->count())->toBe(1);
});

// ── Authorization ───────────────────────────────────────────────────────────

it('denies request/complete without their permissions and across branches', function () {
    $this->seed(ShieldPermissionSeeder::class);

    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $application = reviewApplication(['branch_id' => $branch->id]);

    $noPerm = User::factory()->create(['branch_id' => $branch->id]);
    $branchStaff = User::factory()->create(['branch_id' => $branch->id]);
    $branchStaff->assignRole('branch_staff');
    $crossBranch = User::factory()->create(['branch_id' => $otherBranch->id]);
    $crossBranch->assignRole('branch_staff');

    expect($noPerm->can('requestCorrection', $application))->toBeFalse()
        ->and($noPerm->can('completeCorrection', $application))->toBeFalse()
        ->and($branchStaff->can('requestCorrection', $application))->toBeTrue()
        ->and($branchStaff->can('completeCorrection', $application))->toBeTrue()
        ->and($crossBranch->can('requestCorrection', $application))->toBeFalse();
});

it('completed corrections are immutable and cannot be deleted', function () {
    $this->actingAs(User::factory()->create());
    $application = reviewApplication();
    openCorrectionOn($application);
    $application->update(['father_phone' => '900111222']);
    $result = app(CompleteCorrectionAction::class)->handle($application->fresh(), [0]);

    $correction = $result->corrections()->first();

    expect(fn () => $correction->update(['reason' => 'tampered']))->toThrow(RuntimeException::class);
    expect(fn () => $correction->delete())->toThrow(RuntimeException::class);
});
