<?php

use App\Actions\Applications\SignContractOnlineAction;
use App\Actions\Applications\UploadSignedContractAction;
use App\Actions\Support\CreatePdfAction;
use App\Exceptions\StaleApplicationStateException;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\Models\Student;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\Cancelled;
use App\States\Applications\Rejected;
use Illuminate\Support\Facades\Storage;

function signatureData(): string
{
    // A genuine 1x1 PNG, not merely bytes behind a matching data-URI prefix.
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
}

function fakePdfBinding(): void
{
    app()->bind(CreatePdfAction::class, fn () => new class
    {
        public function execute(string $view, string $path, array $data): string
        {
            Storage::disk('public')->put($path, 'pdf-bytes');

            return Storage::url($path);
        }
    });
}

it('accepts once and rejects a stale acceptance replay', function () {
    $application = Application::factory()->awaitingBranchReview()->create();
    $stale = Application::find($application->id);

    $application->status->transitionTo(Accepted::class);

    expect(fn () => $stale->status->transitionTo(Accepted::class))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->activities()->where('to_state', Accepted::getMorphClass())->count())->toBe(1)
        ->and(Student::count())->toBe(1);
});

it('rejects once and blocks a stale rejection replay without overwriting the reason', function () {
    $application = Application::factory()->awaitingBranchReview()->create();
    $stale = Application::find($application->id);

    $application->status->transitionTo(Rejected::class, 'first reason');

    expect(fn () => $stale->status->transitionTo(Rejected::class, 'second reason'))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->rejection_reason)->toBe('first reason')
        ->and($application->fresh()->activities()->where('to_state', Rejected::getMorphClass())->count())->toBe(1);
});

it('cancels once and blocks a stale cancellation replay', function () {
    $application = Application::factory()->awaitingBranchReview()->create();
    $stale = Application::find($application->id);

    $application->status->transitionTo(Cancelled::class, 'first note');

    expect(fn () => $stale->status->transitionTo(Cancelled::class, 'second note'))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->activities()->where('to_state', Cancelled::getMorphClass())->count())->toBe(1);
});

it('generates the contract once and rejects a stale completion-to-signature replay', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();
    $stale = Application::find($application->id);

    $application->status->transitionTo(AwaitingContractSignature::class);
    $firstToken = $application->fresh()->activeContract->token;

    expect(fn () => $stale->status->transitionTo(AwaitingContractSignature::class))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->activeContract->token)->toBe($firstToken)
        ->and($application->fresh()->activities()->where('to_state', AwaitingContractSignature::getMorphClass())->count())->toBe(1);
});

it('reopens once and rejects a stale signature-to-completion replay', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $stale = Application::find($application->id);

    $application->status->transitionTo(AwaitingApplicationCompletion::class);

    expect(fn () => $stale->status->transitionTo(AwaitingApplicationCompletion::class))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->activeContract)->toBeNull()
        ->and($application->fresh()->activities()->where('to_state', AwaitingApplicationCompletion::getMorphClass())->count())->toBe(1);
});

it('signs online once and rejects a stale online-signing replay without overwriting artifacts', function () {
    Storage::fake('public');
    fakePdfBinding();

    $application = Application::factory()->awaitingContractSignature()->create();
    $staleContract = ApplicationContract::with('application')->find($application->activeContract->id);

    app(SignContractOnlineAction::class)->execute($application->activeContract, $application->activeContract->token, signatureData());

    $firstFilePath = $application->fresh()->activeContract->file_path;

    expect(fn () => app(SignContractOnlineAction::class)->execute($staleContract, $staleContract->token, signatureData()))
        ->toThrow(StaleApplicationStateException::class);

    expect($application->fresh()->activeContract->file_path)->toBe($firstFilePath)
        ->and($application->activities()->where('to_state', AwaitingBranchReview::getMorphClass())->count())->toBe(1);
});

it('rejects signing with a token invalidated by a reopen + regenerate cycle, never signing the replacement contract', function () {
    Storage::fake('public');
    fakePdfBinding();

    $application = Application::factory()->awaitingContractSignature()->create();

    // Load the old token/request exactly as the controller would have, before anything else
    // happens to this application.
    $oldToken = $application->activeContract->token;
    $staleApplicationContract = ApplicationContract::with('application')->where('token', $oldToken)->first();

    // Concurrently: reopen data entry (invalidates the token) and regenerate the contract
    // (issues a brand-new token on the same contract row). Each transitionTo() operates on a
    // freshly locked clone, not this in-memory instance, so it must be refreshed in between.
    $application->status->transitionTo(AwaitingApplicationCompletion::class);
    $application->refresh();
    $application->status->transitionTo(AwaitingContractSignature::class);
    $newToken = $application->fresh()->activeContract->token;

    expect($newToken)->not->toBeNull()->not->toBe($oldToken);

    expect(fn () => app(SignContractOnlineAction::class)->execute($staleApplicationContract, $oldToken, signatureData()))
        ->toThrow(InvalidArgumentException::class);

    expect($application->fresh()->activeContract->token)->toBe($newToken)
        ->and($application->fresh()->activeContract->signed_at)->toBeNull()
        ->and($application->fresh()->activeContract->isSignedOff())->toBeFalse()
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->activities()->where('to_state', AwaitingBranchReview::getMorphClass())->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('records an uploaded signature once and rejects a stale upload replay', function () {
    Storage::fake('public');
    Storage::disk('public')->put('contracts/uploads/first.pdf', 'first');
    Storage::disk('public')->put('contracts/uploads/second.pdf', 'second');

    $application = Application::factory()->awaitingContractSignature()->create();
    $stale = Application::find($application->id);

    app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/first.pdf');

    expect(fn () => app(UploadSignedContractAction::class)->execute($stale, 'contracts/uploads/second.pdf'))
        ->toThrow(StaleApplicationStateException::class);

    // The stale replay's own distinct candidate is left in place, not deleted: this action
    // never created it and cannot prove exclusive ownership, so a failure is reported, not
    // compensated by storage deletion. It becomes a later cleanup job's concern.
    expect($application->fresh()->activeContract->file_path)->toBe('contracts/uploads/first.pdf')
        ->and(Storage::disk('public')->exists('contracts/uploads/second.pdf'))->toBeTrue()
        ->and($application->activities()->where('to_state', AwaitingBranchReview::getMorphClass())->count())->toBe(1);
});

it('does not delete the winning artifact when a stale upload replay reuses the same path', function () {
    Storage::fake('public');
    Storage::disk('public')->put('contracts/uploads/shared.pdf', 'shared-bytes');

    $application = Application::factory()->awaitingContractSignature()->create();
    $stale = Application::find($application->id);

    app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/shared.pdf');

    expect(fn () => app(UploadSignedContractAction::class)->execute($stale, 'contracts/uploads/shared.pdf'))
        ->toThrow(StaleApplicationStateException::class);

    expect(Storage::disk('public')->exists('contracts/uploads/shared.pdf'))->toBeTrue()
        ->and($application->fresh()->activeContract->file_path)->toBe('contracts/uploads/shared.pdf');
});

it('compensates the signature file when PDF generation fails', function () {
    Storage::fake('public');
    app()->bind(CreatePdfAction::class, fn () => new class
    {
        public function execute(string $view, string $path, array $data): string
        {
            throw new RuntimeException('pdf generation failed');
        }
    });

    $application = Application::factory()->awaitingContractSignature()->create();

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->activeContract, $application->activeContract->token, signatureData()))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and($application->fresh()->activeContract->signed_at)->toBeNull()
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});
