<?php

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Actions\Applications\SignContractOnlineAction;
use App\Actions\Applications\UploadSignedContractAction;
use App\Actions\Support\CreatePdfAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
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
    return 'data:image/png;base64,'.base64_encode('fake-png-bytes');
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

    app(SignContractOnlineAction::class)->execute($application->contract, signature(), 'contract body');

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

    expect(fn () => app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/x.pdf'))
        ->toThrow(ApplicationIncompleteException::class);
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

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->contract, signature(), 'contract body'))
        ->toThrow(RuntimeException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->contract->signed_at)->toBeNull()
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
