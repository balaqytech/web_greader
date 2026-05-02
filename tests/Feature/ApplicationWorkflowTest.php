<?php

use App\Models\Application;
use App\Models\Student;
use App\States\Applications\Accepted;
use App\States\Applications\Draft;
use App\States\Applications\Rejected;
use App\States\Applications\Submitted;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an application in draft status', function () {
    $application = Application::factory()->create();

    expect($application->status)->toBeInstanceOf(Draft::class)
        ->and($application->ref_no)->toStartWith('APP-');
});

it('validates completion before transitioning to submitted', function () {
    $application = Application::factory()->create();

    // The factory creates the student and 3 contacts (1 guardian) automatically in afterCreating
    $application->status->transitionTo(Submitted::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Submitted::class);
});

it('generates a contract and transitions to waiting contract signature', function () {
    $application = Application::factory()->submitted()->create();

    $application->status->transitionTo(WaitingContractSignature::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(WaitingContractSignature::class)
        ->and($application->contract)->not->toBeNull()
        ->and($application->contract->token)->not->toBeNull();
});

it('validates contract is signed before review', function () {
    $application = Application::factory()->waitingContractSignature()->create();

    // Contract is generated but not signed yet
    expect(fn () => $application->status->transitionTo(UnderReview::class))
        ->toThrow(Exception::class, 'Application contract must be signed before review.');

    // Sign the contract
    $application->contract->update([
        'signed_at' => now(),
        'signed_by_applicant' => true,
    ]);

    $application->status->transitionTo(UnderReview::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(UnderReview::class);
});

it('accepts an application and creates student and guardian', function () {
    $application = Application::factory()->underReview()->create();

    $application->status->transitionTo(Accepted::class);
    $application->refresh();

    $civilNumber = $application->applicationStudent->civil_number;
    $student = Student::where('civil_number', $civilNumber)->first();

    expect($application->status)->toBeInstanceOf(Accepted::class)
        ->and($student)->not->toBeNull()
        ->and($student->guardian_id)->not->toBeNull()
        ->and($student->contacts)->toHaveCount(3);
});

it('rejects an application', function () {
    $application = Application::factory()->underReview()->create();
    $application->rejection_reason = 'Not eligible';
    $application->save();

    $application->status->transitionTo(Rejected::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Rejected::class)
        ->and($application->rejection_reason)->toBe('Not eligible');
});
