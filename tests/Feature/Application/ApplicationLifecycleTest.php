<?php

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

uses(RefreshDatabase::class);

it('creates an application at the registration-fee gate by default', function () {
    $application = Application::factory()->create();

    expect($application->status)->toBeInstanceOf(AwaitingRegistrationFee::class)
        ->and($application->ref_no)->toStartWith('APP-');
});

it('generates a contract when advancing from completion to contract signature', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();

    $application->status->transitionTo(AwaitingContractSignature::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->contract)->not->toBeNull()
        ->and($application->contract->token)->not->toBeNull()
        ->and($application->activities()->where('to_state', AwaitingContractSignature::getMorphClass())->exists())->toBeTrue();
});

it('blocks advancing to contract signature when required data is incomplete', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create([
        'student_civil_number' => null,
    ]);

    expect(fn () => $application->status->transitionTo(AwaitingContractSignature::class))
        ->toThrow(ApplicationIncompleteException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

it('signs off contract signature into branch review', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    $application->status->transitionTo(AwaitingBranchReview::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingBranchReview::class);
});

it('reopens data entry from contract signature and invalidates the contract token', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    $application->status->transitionTo(AwaitingApplicationCompletion::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and($application->contract->token)->toBeNull();
});

it('accepts an application with a signed contract', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $application->status->transitionTo(Accepted::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Accepted::class)
        ->and($application->student_id)->not->toBeNull();
});

it('blocks acceptance when the contract is not signed', function () {
    $application = Application::factory()->awaitingBranchReview(signed: false)->create();

    expect(fn () => $application->status->transitionTo(Accepted::class))
        ->toThrow(ApplicationIncompleteException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingBranchReview::class);
});

it('rejects an application from branch review', function () {
    $application = Application::factory()->awaitingBranchReview()->create(['rejection_reason' => 'Not eligible']);

    $application->status->transitionTo(Rejected::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Rejected::class)
        ->and($application->rejection_reason)->toBe('Not eligible');
});

it('cancels applications from every non-terminal baseline state', function (string $factoryState) {
    $application = Application::factory()->{$factoryState}()->create();

    $application->status->transitionTo(Cancelled::class);

    expect($application->fresh()->status)->toBeInstanceOf(Cancelled::class);
})->with([
    'awaitingRegistrationFee',
    'awaitingApplicationCompletion',
    'awaitingContractSignature',
    'awaitingBranchReview',
]);

it('does not register the payment-gated fee transition in Phase 0', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect($application->status->canTransitionTo(AwaitingApplicationCompletion::class))->toBeFalse();

    expect(fn () => $application->status->transitionTo(AwaitingApplicationCompletion::class))
        ->toThrow(TransitionNotFound::class);
});

it('does not register the correction transitions in Phase 0', function () {
    $review = Application::factory()->awaitingBranchReview()->create();
    expect($review->status->canTransitionTo(CorrectionRequested::class))->toBeFalse();

    $correction = Application::factory()->create(['status' => CorrectionRequested::$name]);
    expect($correction->status->canTransitionTo(AwaitingBranchReview::class))->toBeFalse()
        ->and($correction->status->canTransitionTo(AwaitingContractSignature::class))->toBeFalse();
});
