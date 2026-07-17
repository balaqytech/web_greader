<?php

declare(strict_types=1);

use App\Actions\Documents\ApproveDocumentAction;
use App\Actions\Documents\RejectDocumentAction;
use App\Exceptions\DocumentReviewException;
use App\Exceptions\DocumentUploadException;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\User;
use App\States\Documents\Approved;
use App\States\Documents\Missing;
use App\States\Documents\Rejected;
use App\States\Documents\Uploaded;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function uploadedDocument(): ApplicationDocument
{
    $document = ApplicationDocument::factory()->create(['status' => Missing::$name]);
    $file = ApplicationDocumentFile::factory()->for($document, 'document')->create();
    Storage::disk('local')->put($file->file_path, "%PDF-1.4\nreview");
    $document->update(['current_file_id' => $file->id]);
    $document->status->transitionTo(Uploaded::class);

    return $document->fresh();
}

it('approves an uploaded document and records the reviewer', function () {
    $document = uploadedDocument();
    $reviewer = User::factory()->create();

    $result = app(ApproveDocumentAction::class)->execute($document, $reviewer);

    expect($result->status)->toBeInstanceOf(Approved::class)
        ->and($result->reviewed_by)->toBe($reviewer->id)
        ->and($result->reviewed_at)->not->toBeNull()
        ->and($result->rejection_reason)->toBeNull();
});

it('rejects an uploaded document with a mandatory reason', function () {
    $document = uploadedDocument();
    $reviewer = User::factory()->create();

    $result = app(RejectDocumentAction::class)->execute($document, $reviewer, 'Photo is unreadable');

    expect($result->status)->toBeInstanceOf(Rejected::class)
        ->and($result->reviewed_by)->toBe($reviewer->id)
        ->and($result->rejection_reason)->toBe('Photo is unreadable');
});

it('refuses a rejection with an empty reason', function () {
    $document = uploadedDocument();

    expect(fn () => app(RejectDocumentAction::class)->execute($document, User::factory()->create(), '   '))
        ->toThrow(DocumentReviewException::class);

    expect($document->fresh()->status)->toBeInstanceOf(Uploaded::class);
});

it('refuses to approve a document that is not uploaded', function () {
    $document = ApplicationDocument::factory()->create(['status' => Missing::$name]);

    expect(fn () => app(ApproveDocumentAction::class)->execute($document, User::factory()->create()))
        ->toThrow(DocumentReviewException::class);
});

it('refuses to reject a document that is not uploaded', function () {
    $document = ApplicationDocument::factory()->create(['status' => Missing::$name]);

    expect(fn () => app(RejectDocumentAction::class)->execute($document, User::factory()->create(), 'reason'))
        ->toThrow(DocumentReviewException::class);
});

it('refuses to review a document that a concurrent upload has already replaced past uploaded', function () {
    $document = uploadedDocument();
    // Simulate a stale caller: the persisted state has since moved on to approved.
    app(ApproveDocumentAction::class)->execute($document, User::factory()->create());
    $stale = ApplicationDocument::withoutGlobalScopes()->find($document->id);
    $stale->setRawAttributes(array_merge($stale->getAttributes(), ['status' => Uploaded::$name]), true);

    expect(fn () => app(ApproveDocumentAction::class)->execute($stale, User::factory()->create()))
        ->toThrow(DocumentReviewException::class);
});

it('refuses to enter uploaded state without a persisted current file', function () {
    $document = ApplicationDocument::factory()->create(['status' => Missing::$name]);

    expect(fn () => $document->status->transitionTo(Uploaded::class))
        ->toThrow(DocumentUploadException::class);

    expect($document->fresh()->status)->toBeInstanceOf(Missing::class);
});

it('refuses to approve without complete reviewer metadata', function () {
    $document = uploadedDocument();

    expect(fn () => $document->status->transitionTo(Approved::class))
        ->toThrow(DocumentReviewException::class);

    expect($document->fresh()->status)->toBeInstanceOf(Uploaded::class);
});

it('refuses to reject without a non-empty reason', function () {
    $document = uploadedDocument();
    $document->update([
        'reviewed_by' => User::factory()->create()->id,
        'reviewed_at' => now(),
        'rejection_reason' => null,
    ]);

    expect(fn () => $document->fresh()->status->transitionTo(Rejected::class))
        ->toThrow(DocumentReviewException::class);

    expect($document->fresh()->status)->toBeInstanceOf(Uploaded::class);
});
