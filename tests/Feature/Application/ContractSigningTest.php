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

    expect($application->activeContract)->toBeNull();

    expect(fn () => $application->status->transitionTo(AwaitingBranchReview::class))
        ->toThrow(ApplicationIncompleteException::class);
});

it('signs the contract electronically and advances to branch review', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    app(SignContractOnlineAction::class)->execute($application->activeContract, $application->activeContract->token, signature());

    $application->refresh();
    $contract = $application->activeContract;

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($contract->signed_at)->not->toBeNull()
        ->and($contract->signed_by_applicant)->toBeTrue()
        ->and($contract->signature_path)->not->toBeNull()
        ->and($contract->file_path)->not->toBeNull()
        ->and($contract->isSignedOff())->toBeTrue()
        ->and($application->activities()->where('to_state', AwaitingBranchReview::getMorphClass())->exists())->toBeTrue();
});

it('embeds a signature URL that resolves through the public disk, not the default disk', function () {
    config(['filesystems.default' => 'local']);
    // Storage::fake() (called for 'public' in beforeEach) does not preserve the disk's own
    // 'url' config, so it must be passed back explicitly here or this test cannot distinguish
    // the 'public' disk's URL shape from the default disk's.
    Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);

    $application = Application::factory()->awaitingContractSignature()->create();

    $capturedSignatureUrl = null;

    $pdfMock = Mockery::mock(CreatePdfAction::class);
    $pdfMock->shouldReceive('execute')
        ->once()
        ->withArgs(function (string $view, string $path, array $data) use (&$capturedSignatureUrl) {
            $capturedSignatureUrl = $data['signature'];

            return true;
        })
        ->andReturnUsing(function (string $view, string $path) {
            Storage::disk('public')->put($path, 'pdf-bytes');

            return Storage::disk('public')->url($path);
        });
    app()->instance(CreatePdfAction::class, $pdfMock);

    app(SignContractOnlineAction::class)->execute($application->activeContract, $application->activeContract->token, signature());

    // Storage::url() on the default ('local') disk resolves through a different serving
    // route ('/storage/...' with no host) than 'public' ('http://.../storage/...').
    expect($capturedSignatureUrl)->toStartWith(rtrim(config('app.url'), '/').'/storage/')
        ->and($capturedSignatureUrl)->not->toStartWith('/storage/');
});

it('records a staff-uploaded signed copy and advances to branch review', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    Storage::disk('public')->put('contracts/uploads/signed.pdf', 'signed');

    app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/signed.pdf');

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->activeContract->file_path)->toBe('contracts/uploads/signed.pdf')
        ->and($application->activeContract->signed_at)->not->toBeNull()
        ->and($application->activeContract->isSignedOff())->toBeTrue();
});

it('rejects an uploaded signed copy when the contract is missing, leaving the candidate as an orphan', function () {
    $application = Application::factory()->create(['status' => AwaitingContractSignature::$name]);
    Storage::disk('public')->put('contracts/uploads/x.pdf', 'uploaded');

    expect(fn () => app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/x.pdf'))
        ->toThrow(ApplicationIncompleteException::class);

    // A distinct failed candidate is not automatically deleted: this action cannot prove it
    // has exclusive ownership of a file it never wrote itself. Reclaiming it is a separate,
    // age-threshold cleanup job's concern, not this action's.
    expect(Storage::disk('public')->exists('contracts/uploads/x.pdf'))->toBeTrue();
});

it('rejects an upload when the referenced storage path does not exist', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    expect(fn () => app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/does-not-exist.pdf'))
        ->toThrow(ApplicationIncompleteException::class);

    expect($application->fresh()->activeContract->file_path)->toBeNull()
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('rejects signing when the candidate file is deleted between the initial call and locked persistence', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    // Simulate a separate process removing the candidate in the window between this action's
    // initial fail-fast check (before any lock) and the authoritative re-check taken under
    // the application/contract locks, immediately before persisting.
    $fakeDisk = Mockery::mock(Cloud::class);
    $fakeDisk->shouldReceive('exists')->twice()->andReturn(true, false);
    Storage::shouldReceive('disk')->with('public')->andReturn($fakeDisk);

    expect(fn () => app(UploadSignedContractAction::class)->execute($application, 'contracts/uploads/vanishing.pdf'))
        ->toThrow(ApplicationIncompleteException::class);

    expect($application->fresh()->activeContract->file_path)->toBeNull()
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

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->activeContract, $application->activeContract->token, signature()))
        ->toThrow(RuntimeException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->activeContract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('throws when the signature write returns false, leaving no state changes', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    $fakeDisk = Mockery::mock(Cloud::class);
    $fakeDisk->shouldReceive('put')->andReturn(false);
    $fakeDisk->shouldReceive('delete')->andReturn(true);
    Storage::shouldReceive('disk')->with('public')->andReturn($fakeDisk);

    expect(fn () => app(SignContractOnlineAction::class)->execute(
        $application->activeContract, $application->activeContract->token, signature(),
    ))->toThrow(RuntimeException::class);

    expect($application->fresh()->activeContract->signed_at)->toBeNull()
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('rejects a signature with invalid base64 characters, leaving no artifacts', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    expect(fn () => app(SignContractOnlineAction::class)->execute(
        $application->activeContract,
        $application->activeContract->token,
        'data:image/png;base64,not!!valid==base64',
    ))->toThrow(InvalidArgumentException::class);

    expect($application->fresh()->activeContract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('rejects a signature payload that is not a real PNG image, leaving no artifacts', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $notAnImage = 'data:image/png;base64,'.base64_encode('this is definitely not a png file');

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->activeContract, $application->activeContract->token, $notAnImage))
        ->toThrow(InvalidArgumentException::class);

    expect($application->fresh()->activeContract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('rejects an oversized signature payload, leaving no artifacts', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $oversized = 'data:image/png;base64,'.str_repeat('A', 2_000_001);

    expect(fn () => app(SignContractOnlineAction::class)->execute($application->activeContract, $application->activeContract->token, $oversized))
        ->toThrow(InvalidArgumentException::class);

    expect($application->fresh()->activeContract->signed_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('rolls back the DB state when the upload transaction fails, leaving the candidate file untouched', function () {
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

    // This action never created the candidate file and cannot prove exclusive ownership of
    // it, so a failure must never delete it — only report the signing failure. It is left as
    // an orphan for a later age-threshold cleanup job.
    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->activeContract->file_path)->toBeNull()
        ->and(Storage::disk('public')->exists('contracts/uploads/signed.pdf'))->toBeTrue();
});
