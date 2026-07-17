<?php

declare(strict_types=1);

use App\Actions\Documents\EvaluateDocumentRequirementsAction;
use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;
use App\States\Documents\Approved;
use App\States\Documents\Rejected;
use App\States\Documents\Uploaded;

function synced(bool $transfer = false): Application
{
    $application = Application::factory()->awaitingApplicationCompletion()->create(['is_transfer_student' => $transfer]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    return $application;
}

function requirement(Application $application, DocumentType $type): ApplicationDocument
{
    return ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->where('type', $type)
        ->first();
}

function evaluate(Application $application)
{
    return app(EvaluateDocumentRequirementsAction::class)->execute($application);
}

it('warns about every requirement while all documents are missing', function () {
    $application = synced();

    $summary = evaluate($application);

    // Eight documents collapse into seven logical requirements (civil id + passport = one).
    expect($summary->warnings())->toHaveCount(7)
        ->and($summary->isComplete())->toBeFalse();
});

it('treats an uploaded document as satisfying its requirement', function () {
    $application = synced();
    requirement($application, DocumentType::BirthCertificate)->status->transitionTo(Uploaded::class);

    $keys = evaluate($application)->warnings()->pluck('key');

    expect($keys)->not->toContain(DocumentType::BirthCertificate->value);
});

it('lets either identity member satisfy the whole identity group', function () {
    $application = synced();
    requirement($application, DocumentType::Passport)->status->transitionTo(Uploaded::class);

    $keys = evaluate($application)->warnings()->pluck('key');

    expect($keys)->not->toContain('student_identity');
});

it('still warns for the identity group while both members are missing', function () {
    $application = synced();

    $keys = evaluate($application)->warnings()->pluck('key');

    expect($keys)->toContain('student_identity');
});

it('keeps warning when a document is rejected', function () {
    $application = synced();
    $document = requirement($application, DocumentType::PersonalPhoto);
    $document->status->transitionTo(Uploaded::class);
    $document->status->transitionTo(Rejected::class);

    $keys = evaluate($application)->warnings()->pluck('key');

    expect($keys)->toContain(DocumentType::PersonalPhoto->value);
});

it('counts an approved document as satisfied', function () {
    $application = synced();
    $document = requirement($application, DocumentType::VaccinationCard);
    $document->status->transitionTo(Uploaded::class);
    $document->status->transitionTo(Approved::class);

    $keys = evaluate($application)->warnings()->pluck('key');

    expect($keys)->not->toContain(DocumentType::VaccinationCard->value);
});

it('never warns about an optional transfer file', function () {
    $application = synced(transfer: true);
    $application->update(['is_transfer_student' => false]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    $keys = evaluate($application)->warnings()->pluck('key');

    expect($keys)->not->toContain(DocumentType::TransferFile->value);
});

it('reports complete once every logical requirement is present', function () {
    $application = synced();

    ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->get()
        ->each(fn (ApplicationDocument $document) => $document->status->transitionTo(Uploaded::class));

    expect(evaluate($application)->isComplete())->toBeTrue();
});
