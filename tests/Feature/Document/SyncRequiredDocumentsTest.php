<?php

declare(strict_types=1);

use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\Scopes\BranchScope;
use App\States\Documents\Uploaded;

function syncDocuments(Application $application): void
{
    app(SyncRequiredDocumentsAction::class)->execute($application);
}

function documentsOf(Application $application)
{
    return ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id);
}

it('creates eight required documents for a non-transfer student', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => false]);

    syncDocuments($application);

    expect(documentsOf($application)->count())->toBe(8)
        ->and(documentsOf($application)->where('type', DocumentType::TransferFile->value)->exists())->toBeFalse();
});

it('creates all nine required documents for a transfer student', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => true]);

    syncDocuments($application);

    expect(documentsOf($application)->count())->toBe(9)
        ->and(documentsOf($application)->where('type', DocumentType::TransferFile->value)->where('is_required', true)->exists())->toBeTrue();
});

it('configures the identity group on both civil id and passport rows', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();

    syncDocuments($application);

    $identity = documentsOf($application)
        ->whereIn('type', [DocumentType::StudentCivilId->value, DocumentType::Passport->value])
        ->get();

    expect($identity)->toHaveCount(2)
        ->and($identity->every(fn ($doc): bool => $doc->requirement_group === 'student_identity'))->toBeTrue()
        ->and($identity->every(fn ($doc): bool => $doc->is_required === true))->toBeTrue();
});

it('is idempotent — re-running creates no duplicate rows', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => true]);

    syncDocuments($application);
    syncDocuments($application);
    syncDocuments($application);

    expect(documentsOf($application)->count())->toBe(9);
});

it('retains an uploaded transfer file but marks it optional when transfer status is turned off', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => true]);
    syncDocuments($application);

    $transfer = documentsOf($application)->where('type', DocumentType::TransferFile->value)->first();
    $transfer->status->transitionTo(Uploaded::class);
    ApplicationDocumentFile::factory()->for($transfer, 'document')->create();

    $application->update(['is_transfer_student' => false]);
    syncDocuments($application);

    $transfer->refresh();

    expect(documentsOf($application)->count())->toBe(9)
        ->and($transfer->is_required)->toBeFalse()
        ->and($transfer->status)->toBeInstanceOf(Uploaded::class)
        ->and($transfer->files()->count())->toBe(1);
});

it('reactivates the same transfer row when transfer status is turned back on', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => true]);
    syncDocuments($application);
    $originalId = documentsOf($application)->where('type', DocumentType::TransferFile->value)->value('id');

    $application->update(['is_transfer_student' => false]);
    syncDocuments($application);

    $application->update(['is_transfer_student' => true]);
    syncDocuments($application);

    $transfer = documentsOf($application)->where('type', DocumentType::TransferFile->value)->first();

    expect($transfer->id)->toBe($originalId)
        ->and($transfer->is_required)->toBeTrue()
        ->and(documentsOf($application)->where('type', DocumentType::TransferFile->value)->count())->toBe(1);
});

it('denormalises the application branch onto every created document', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();

    syncDocuments($application);

    expect(documentsOf($application)->get()->every(fn ($doc): bool => $doc->branch_id === $application->branch_id))->toBeTrue();
});
