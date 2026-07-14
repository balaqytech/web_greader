<?php

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;

it('rejects with a supplied reason and records it atomically', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $application->status->transitionTo(Rejected::class, 'Missing documents');
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Rejected::class)
        ->and($application->rejection_reason)->toBe('Missing documents')
        ->and($application->activities()->where('to_state', Rejected::getMorphClass())->exists())->toBeTrue();
});

it('rejects using a reason already set on the record', function () {
    $application = Application::factory()->awaitingBranchReview()->create(['rejection_reason' => 'Not eligible']);

    $application->status->transitionTo(Rejected::class);

    expect($application->fresh()->rejection_reason)->toBe('Not eligible');
});

it('refuses rejection without a reason and leaves no state or audit change', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    expect(fn () => $application->status->transitionTo(Rejected::class))
        ->toThrow(ApplicationIncompleteException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->rejection_reason)->toBeNull()
        ->and($application->activities()->count())->toBe(0);
});

it('cancels with a note and records it atomically', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $application->status->transitionTo(Cancelled::class, 'Family withdrew');
    $application->refresh();

    expect($application->status)->toBeInstanceOf(Cancelled::class)
        ->and($application->activities()->where('to_state', Cancelled::getMorphClass())->exists())->toBeTrue();
});

it('refuses cancellation without a note and leaves no state or audit change', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    expect(fn () => $application->status->transitionTo(Cancelled::class))
        ->toThrow(ApplicationIncompleteException::class);
    expect(fn () => $application->status->transitionTo(Cancelled::class, '   '))
        ->toThrow(ApplicationIncompleteException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->activities()->count())->toBe(0);
});

it('registers cancellation from correction requested', function () {
    $application = Application::factory()->create(['status' => CorrectionRequested::$name]);

    expect($application->status->canTransitionTo(Cancelled::class))->toBeTrue();

    $application->status->transitionTo(Cancelled::class, 'Withdrawn');

    expect($application->fresh()->status)->toBeInstanceOf(Cancelled::class);
});
