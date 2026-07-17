<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;

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
