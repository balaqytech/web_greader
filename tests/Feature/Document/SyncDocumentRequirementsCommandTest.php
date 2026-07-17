<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;
use App\States\Applications\Cancelled;

function backfillCount(Application $application): int
{
    return ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->count();
}

it('backfills requirements onto post-fee applications', function () {
    $completion = Application::factory()->awaitingApplicationCompletion()->create();
    $contract = Application::factory()->awaitingContractSignature()->create(['is_transfer_student' => true]);
    $accepted = Application::factory()->accepted()->create();

    $this->artisan('applications:sync-document-requirements')->assertSuccessful();

    expect(backfillCount($completion))->toBe(8)
        ->and(backfillCount($contract))->toBe(9)
        ->and(backfillCount($accepted))->toBe(8);
});

it('skips pre-fee applications', function () {
    $preFee = Application::factory()->awaitingRegistrationFee()->create();

    $this->artisan('applications:sync-document-requirements')->assertSuccessful();

    expect(backfillCount($preFee))->toBe(0);
});

it('includes rejected applications and cancellations that reached data completion', function () {
    $rejected = Application::factory()->rejected()->create();
    $postFeeCancelled = Application::factory()->awaitingApplicationCompletion()->create();
    $postFeeCancelled->status->transitionTo(Cancelled::class, 'withdrawn after fee');
    $preFeeCancelled = Application::factory()->awaitingRegistrationFee()->create();
    $preFeeCancelled->status->transitionTo(Cancelled::class, 'withdrawn before fee');

    $this->artisan('applications:sync-document-requirements')->assertSuccessful();

    expect(backfillCount($rejected))->toBe(8)
        ->and(backfillCount($postFeeCancelled))->toBe(8)
        ->and(backfillCount($preFeeCancelled))->toBe(0);
});

it('recognises legacy post-fee activity values on cancelled applications', function () {
    $application = Application::factory()->cancelled()->create();
    $application->activities()->create([
        'from_state' => 'submitted',
        'to_state' => 'cancelled',
        'transitioned_at' => now(),
    ]);

    $this->artisan('applications:sync-document-requirements')->assertSuccessful();

    expect(backfillCount($application))->toBe(8);
});

it('restricts the backfill to a single application when the option is given', function () {
    $target = Application::factory()->awaitingApplicationCompletion()->create();
    $other = Application::factory()->awaitingApplicationCompletion()->create();

    $this->artisan('applications:sync-document-requirements', ['--application' => $target->id])
        ->assertSuccessful();

    expect(backfillCount($target))->toBe(8)
        ->and(backfillCount($other))->toBe(0);
});

it('is idempotent across repeated runs', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => true]);

    $this->artisan('applications:sync-document-requirements')->assertSuccessful();
    $this->artisan('applications:sync-document-requirements')->assertSuccessful();

    expect(backfillCount($application))->toBe(9);
});

it('honours a small chunk size over many applications', function () {
    Application::factory()->count(5)->awaitingApplicationCompletion()->create();

    $this->artisan('applications:sync-document-requirements', ['--chunk' => 2])->assertSuccessful();

    expect(ApplicationDocument::withoutGlobalScope(BranchScope::class)->count())->toBe(40);
});
