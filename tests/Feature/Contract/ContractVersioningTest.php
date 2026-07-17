<?php

declare(strict_types=1);

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Actions\Applications\SignContractOnlineAction;
use App\Actions\Contracts\BuildContractSnapshotAction;
use App\Actions\Support\CreatePdfAction;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Contracts\Generated;
use App\States\Contracts\Signed;
use App\States\Contracts\Superseded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function completedApplication(array $overrides = []): Application
{
    return Application::factory()->awaitingApplicationCompletion()->create($overrides);
}

function generateFirstVersion(Application $application): ApplicationContract
{
    return app(GenerateApplicationContractAction::class)->handle($application);
}

// ── Schema ────────────────────────────────────────────────────────────────

it('versions the contracts table with the version/status/snapshot columns and indexes', function () {
    expect(Schema::hasColumns('application_contracts', [
        'version', 'status', 'data_snapshot', 'rendered_body', 'template_hash',
        'generated_by', 'superseded_at', 'superseded_by_contract_id',
    ]))->toBeTrue();

    $indexes = collect(Schema::getIndexes('application_contracts'))->pluck('name');

    expect($indexes)->toContain('application_contracts_application_id_version_unique')
        ->and($indexes)->toContain('application_contracts_application_id_status_index')
        ->and($indexes)->not->toContain('application_contracts_application_id_unique');
});

// ── Generation / snapshot ───────────────────────────────────────────────────

it('stores an immutable snapshot, rendered body, and template hash on generation', function () {
    $application = completedApplication();
    $application->program->update(['contract' => 'Student: $student_name$']);
    $application->refresh();

    $contract = generateFirstVersion($application);

    $expected = app(BuildContractSnapshotAction::class)->handle($application);

    expect($contract->version)->toBe(1)
        ->and($contract->status)->toBeInstanceOf(Generated::class)
        ->and($contract->rendered_body)->toBe($expected->renderedBody)
        ->and($contract->rendered_body)->toContain($application->student_name)
        ->and($contract->template_hash)->toBe($expected->templateHash)
        ->and($contract->data_snapshot['minimum']['student_civil_number'])->toBe($application->student_civil_number)
        ->and($contract->data_snapshot['meta']['backfilled'])->toBeFalse();
});

it('never alters an existing version when the program template is edited after generation', function () {
    $application = completedApplication();
    $application->program->update(['contract' => 'Original $student_name$']);
    $application->refresh();

    $contract = generateFirstVersion($application);
    $body = $contract->rendered_body;
    $hash = $contract->template_hash;

    $application->program->update(['contract' => 'Rewritten completely $student_name$']);
    $contract->refresh();

    expect($contract->rendered_body)->toBe($body)
        ->and($contract->template_hash)->toBe($hash);
});

// ── One active version, supersession, increment ─────────────────────────────

it('supersedes the prior active version and increments the version on regeneration', function () {
    $application = completedApplication();

    $v1 = generateFirstVersion($application);
    $v2 = app(GenerateApplicationContractAction::class)->handle($application);

    $v1->refresh();

    expect($v2->version)->toBe(2)
        ->and($v2->status)->toBeInstanceOf(Generated::class)
        ->and($v1->status)->toBeInstanceOf(Superseded::class)
        ->and($v1->token)->toBeNull()
        ->and($v1->token_expires_at)->toBeNull()
        ->and($v1->superseded_by_contract_id)->toBe($v2->id)
        ->and($application->activeContract->is($v2))->toBeTrue()
        ->and($application->contracts()->count())->toBe(2);
});

it('keeps exactly one active version across repeated generations', function () {
    $application = completedApplication();

    generateFirstVersion($application);
    app(GenerateApplicationContractAction::class)->handle($application);
    app(GenerateApplicationContractAction::class)->handle($application);

    $active = $application->contracts()
        ->whereIn('status', [Generated::$name, Signed::$name])
        ->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->version)->toBe(3);
});

it('rolls back and leaves the predecessor active when generation fails mid-way', function () {
    $application = completedApplication();
    $v1 = generateFirstVersion($application);

    // Force the snapshot build to fail on the *next* generation.
    app()->bind(BuildContractSnapshotAction::class, fn () => new class
    {
        public function handle($application)
        {
            throw new RuntimeException('forced snapshot failure');
        }
    });

    expect(fn () => app(GenerateApplicationContractAction::class)->handle($application))
        ->toThrow(RuntimeException::class);

    $v1->refresh();

    expect($v1->status)->toBeInstanceOf(Generated::class)
        ->and($v1->token)->not->toBeNull()
        ->and($application->contracts()->count())->toBe(1);
});

// ── Reopen supersedes ───────────────────────────────────────────────────────

it('supersedes the generated version on reopen so the next generation is version 2', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $v1 = $application->activeContract;

    $application->status->transitionTo(AwaitingApplicationCompletion::class, 'reopening');
    $application->refresh();
    $v1->refresh();

    expect($v1->status)->toBeInstanceOf(Superseded::class)
        ->and($v1->token)->toBeNull()
        ->and($application->activeContract)->toBeNull();

    $application->status->transitionTo(AwaitingContractSignature::class);
    $application->refresh();

    expect($application->activeContract->version)->toBe(2)
        ->and($application->activeContract->status)->toBeInstanceOf(Generated::class);
});

// ── Token replay ────────────────────────────────────────────────────────────

it('clears the token on a superseded version so its old link can never resolve', function () {
    $application = completedApplication();
    $v1 = generateFirstVersion($application);
    $oldToken = $v1->token;

    app(GenerateApplicationContractAction::class)->handle($application);

    expect(ApplicationContract::where('token', $oldToken)->exists())->toBeFalse();
});

// ── Signing replays the frozen body + normalizes storage ────────────────────

it('signs by replaying the frozen body, stores a disk-relative path, and marks the version signed', function () {
    Storage::fake('public');
    app()->bind(CreatePdfAction::class, fn () => new class
    {
        public function execute(string $view, string $path, array $data): string
        {
            Storage::disk('public')->put($path, 'pdf-bytes');

            return Storage::disk('public')->url($path);
        }
    });

    $application = Application::factory()->awaitingContractSignature()->create();
    $contract = $application->activeContract;

    app(SignContractOnlineAction::class)->execute(
        $contract,
        $contract->token,
        'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
    );

    $contract->refresh();
    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($contract->status)->toBeInstanceOf(Signed::class)
        ->and($contract->file_path)->toStartWith('pdfs/contracts/')
        ->and($contract->file_path)->not->toStartWith('http')
        ->and($contract->signedFileUrl())->toContain($contract->file_path);
});

it('reads a legacy absolute-URL file_path back through the URL helper unchanged', function () {
    $contract = ApplicationContract::factory()->signed()->create([
        'file_path' => 'https://cdn.example.test/contracts/legacy.pdf',
    ]);

    expect($contract->signedFileUrl())->toBe('https://cdn.example.test/contracts/legacy.pdf');
});
