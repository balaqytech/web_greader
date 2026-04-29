<?php

use App\Models\Application;
use App\Models\User;
use App\States\Applications\Accepted;
use App\States\Applications\DataComplete;
use App\States\Applications\PendingRegistration;
use App\States\Applications\Rejected;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

uses(RefreshDatabase::class);

it('creates an application with pending_registration status by default', function () {
    $application = Application::factory()->create();

    expect($application->status)->toBeInstanceOf(PendingRegistration::class)
        ->and($application->ref_no)->toStartWith('APP-');
});

it('transitions from pending_registration to data_complete', function () {
    $application = Application::factory()->create();

    $application->status->transitionTo(DataComplete::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(DataComplete::class);
});

it('transitions from data_complete to waiting_contract', function () {
    $application = Application::factory()->dataComplete()->create();

    $application->status->transitionTo(WaitingContract::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(WaitingContract::class);
});

it('transitions from waiting_contract to under_review', function () {
    $application = Application::factory()->waitingContract()->create();

    $application->status->transitionTo(UnderReview::class, signedByApplicant: true);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(UnderReview::class);
});

it('transitions from waiting_contract to data_complete', function () {
    $application = Application::factory()->waitingContract()->create();

    $application->status->transitionTo(DataComplete::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(DataComplete::class);
});

it('transitions from under_review to accepted', function () {
    $application = Application::factory()->underReview()->create();

    $application->status->transitionTo(Accepted::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Accepted::class);
});

it('transitions from under_review to rejected with a reason', function () {
    $application = Application::factory()->underReview()->create();

    $application->status->transitionTo(Rejected::class, rejectionReason: 'Does not meet age criteria.');
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Rejected::class)
        ->and($application->rejection_reason)->toBe('Does not meet age criteria.');
});

it('transitions from under_review back to pending_registration with notes', function () {
    $application = Application::factory()->underReview()->create();

    $application->status->transitionTo(PendingRegistration::class, notes: 'Missing ID document.');
    $application->refresh();

    expect($application->status)->toBeInstanceOf(PendingRegistration::class)
        ->and($application->rejection_reason)->toBe('Missing ID document.');
});

it('prevents invalid transitions', function () {
    $application = Application::factory()->create(); // pending_registration

    expect(fn() => $application->status->transitionTo(Accepted::class))
        ->toThrow(TransitionNotFound::class);
});

it('generates a unique ref_no per application', function () {
    $a1 = Application::factory()->create();
    $a2 = Application::factory()->create();

    expect($a1->ref_no)->not->toBe($a2->ref_no)
        ->and($a1->ref_no)->toStartWith('APP-')
        ->and($a2->ref_no)->toStartWith('APP-');
});

it('logs an activity entry for each state transition', function () {
    $application = Application::factory()->create();

    $application->status->transitionTo(DataComplete::class);
    $application->refresh();

    expect($application->activities)->toHaveCount(1)
        ->and($application->activities->first()->from_state)->toBe(PendingRegistration::$name)
        ->and($application->activities->first()->to_state)->toBe(DataComplete::$name);
});

it('logs the user who triggered the transition', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create();

    $application->status->transitionTo(DataComplete::class, transitionedBy: $user->id);
    $application->refresh();

    expect($application->activities->first()->transitioned_by)->toBe($user->id);
});

it('logs notes in the activity entry', function () {
    $application = Application::factory()->underReview()->create();

    $application->status->transitionTo(
        Rejected::class,
        rejectionReason: 'Age does not qualify.',
    );
    $application->refresh();

    expect($application->activities->first()->notes)->toBe('Age does not qualify.');
});

it('builds a full activity timeline across multiple transitions', function () {
    $application = Application::factory()->create();

    $application->status->transitionTo(DataComplete::class);
    $application->refresh();
    $application->status->transitionTo(WaitingContract::class);
    $application->refresh();
    $application->status->transitionTo(UnderReview::class, signedByApplicant: true);
    $application->refresh();
    $application->status->transitionTo(Accepted::class);
    $application->refresh();

    expect($application->activities)->toHaveCount(4)
        ->and($application->activities->pluck('to_state')->toArray())->toBe([
            Accepted::$name,
            UnderReview::$name,
            WaitingContract::$name,
            DataComplete::$name,
        ]);
});
