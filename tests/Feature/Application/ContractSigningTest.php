<?php

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\SignContractOnlineAction;
use App\Actions\Applications\UploadSignedContractAction;
use App\Actions\Support\CreatePdfAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    // Avoid real mPDF rendering; write a placeholder to the requested path.
    app()->bind(CreatePdfAction::class, fn () => new class
    {
        public function execute(string $view, string $path, array $data): string
        {
            Storage::disk('public')->put($path, 'pdf-bytes');

            return Storage::url($path);
        }
    });
});

function signature(): string
{
    // A genuine 1x1 PNG, not merely bytes behind a matching data-URI prefix.
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
}

it('rejects the branch-review transition when the contract is unsigned', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    expect(fn () => $application->status->transitionTo(AwaitingBranchReview::class))
        ->toThrow(ApplicationIncompleteException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('rejects the branch-review transition when no contract exists', function () {
    $application = Application::factory()->create(['status' => AwaitingContractSignature::$name]);

    expect($application->contract)->toBeNull();

    expect(fn () => $application->status->transitionTo(AwaitingBranchReview::class))
        ->toThrow(ApplicationIncompleteException::class);
});

it('signs the contract electronically and advances to branch review', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    app(SignContractOnlineAction::class)->execute($application->contract, $application->contract->token, signature());

    $application->refresh();
    $contract = $application->contract;

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($contract->signed_at)->not->toBeNull()
        ->and($contract->signed_by_applicant)->toBeTrue()
        ->and($contract->signature_path)->not->toBeNull()
        ->and($contract->file_path)->not->toBeNull()
        ->and($contract->isSignedOff())->toBeTrue()
        ->and($application->activities()->where('to_state', AwaitingBranchReview::getMorphClass())->exists())->toBeTrue();
});

it('records a staff-uploaded signed copy and advances to branch review', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    Storage::disk('public')->put('contracts/uploads/signed.pdf', 'signed');

    app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/signed.pdf');

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->contract->file_path)->toBe('contracts/uploads/signed.pdf')
        ->and($application->contract->signed_at)->not->toBeNull()
        ->and($application->contract->isSignedOff())->toBeTrue();
});

it('rejects an uploaded signed copy when the contract is missing', function () {
    $application = Application::factory()->create(['status' => AwaitingContractSignature::$name]);
    Storage::disk('public')->put('contracts/uploads/x.pdf', 'uploaded');

    expect(fn () => app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/x.pdf'))
        ->toThrow(ApplicationIncompleteException::class);
});

it('rejects an upload when the referenced storage path does not exist', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    expect(fn () => app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/does-not-exist.pdf'))
        ->toThrow(ApplicationIncompleteException::class);

    expect($application->fresh()->contract->file_path)->toBeNull()
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('compensates signature artifacts and rolls back when the signing transaction fails', function () {
    // Force the activity write (last step inside the transaction) to fail.
    app()->bind(RecordApplicationActivityAction::class, fn () => new class
    {
        public function handle($application, $fromState, $toState, $notes = null)
        {
            throw new RuntimeException('forced failure');
        }
    });

    $application = Application::factory()->awaitingContractSignature()->create();

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->contract, $application->contract->token, signature()))
        ->toThrow(RuntimeException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->contract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('throws when the signature write returns false, leaving no state changes', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    $fakeDisk = Mockery::mock(Cloud::class);
    $fakeDisk->shouldReceive('put')->andReturn(false);
    $fakeDisk->shouldReceive('delete')->andReturn(true);
    Storage::shouldReceive('disk')->with('public')->andReturn($fakeDisk);

    expect(fn () => app(SignContractOnlineAction::class)->execute(
        $application->contract, $application->contract->token, signature(),
    ))->toThrow(RuntimeException::class);

    expect($application->fresh()->contract->signed_at)->toBeNull()
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('rejects a signature with invalid base64 characters, leaving no artifacts', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    expect(fn () => app(SignContractOnlineAction::class)->execute(
        $application->contract,
        $application->contract->token,
        'data:image/png;base64,not!!valid==base64',
    ))->toThrow(InvalidArgumentException::class);

    expect($application->fresh()->contract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('rejects a signature payload that is not a real PNG image, leaving no artifacts', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $notAnImage = 'data:image/png;base64,'.base64_encode('this is definitely not a png file');

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->contract, $application->contract->token, $notAnImage))
        ->toThrow(InvalidArgumentException::class);

    expect($application->fresh()->contract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('rejects an oversized signature payload, leaving no artifacts', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $oversized = 'data:image/png;base64,'.str_repeat('A', 2_000_001);

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->contract, $application->contract->token, $oversized))
        ->toThrow(InvalidArgumentException::class);

    expect($application->fresh()->contract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('compensates the uploaded file and rolls back when the upload transaction fails', function () {
    app()->bind(RecordApplicationActivityAction::class, fn () => new class
    {
        public function handle($application, $fromState, $toState, $notes = null)
        {
            throw new RuntimeException('forced failure');
        }
    });

    $application = Application::factory()->awaitingContractSignature()->create();
    Storage::disk('public')->put('contracts/uploads/signed.pdf', 'signed');

    expect(fn () => app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/signed.pdf'))
        ->toThrow(RuntimeException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->contract->file_path)->toBeNull()
        ->and(Storage::disk('public')->exists('contracts/uploads/signed.pdf'))->toBeFalse();
});
