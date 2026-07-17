<?php

declare(strict_types=1);

use App\Actions\Documents\UploadDocumentAction;
use App\DTOs\Documents\UploadDocumentDTO;
use App\Exceptions\DocumentUploadException;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\States\Documents\Approved;
use App\States\Documents\Missing;
use App\States\Documents\Rejected;
use App\States\Documents\Uploaded;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function stageTempFile(string $name = 'doc.pdf', string $mime = 'application/pdf', int $kilobytes = 100): array
{
    $file = UploadedFile::fake()->create($name, $kilobytes, $mime);
    $path = $file->storeAs('documents/tmp', $name, ['disk' => 'local']);

    return [
        'path' => $path,
        'name' => $name,
        'mime' => $mime,
        'size' => Storage::disk('local')->size($path),
    ];
}

function upload(ApplicationDocument $document, array $temp, ?User $by = null): ApplicationDocumentFile
{
    return app(UploadDocumentAction::class)->execute(new UploadDocumentDTO(
        document: $document,
        temporaryPath: $temp['path'],
        originalName: $temp['name'],
        mimeType: $temp['mime'],
        size: $temp['size'],
        uploadedBy: $by,
    ));
}

it('stores the first upload, moves the document to uploaded, and repoints the current file', function () {
    $document = ApplicationDocument::factory()->create(['status' => Missing::$name]);
    $uploader = User::factory()->create();

    $file = upload($document, stageTempFile(), $uploader);
    $document->refresh();

    expect($document->status)->toBeInstanceOf(Uploaded::class)
        ->and($document->current_file_id)->toBe($file->id)
        ->and($file->original_name)->toBe('doc.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->uploaded_by_id)->toBe($uploader->id)
        ->and(Storage::disk('local')->exists($file->file_path))->toBeTrue();
});

it('generates a random private path namespaced to the application and document', function () {
    $document = ApplicationDocument::factory()->create();

    $file = upload($document, stageTempFile());

    expect($file->file_path)->toStartWith("documents/applications/{$document->application_id}/{$document->id}/")
        ->and($file->file_path)->toEndWith('.pdf');
});

it('creates a new file row on every replacement without rewriting history', function () {
    $document = ApplicationDocument::factory()->create();

    $first = upload($document, stageTempFile('a.pdf'));
    $second = upload($document, stageTempFile('b.pdf'));
    $document->refresh();

    expect($document->files()->count())->toBe(2)
        ->and($document->current_file_id)->toBe($second->id)
        ->and($first->fresh()->file_path)->not->toBe($second->file_path);
});

it('clears the review verdict when a rejected document is re-uploaded', function () {
    $document = ApplicationDocument::factory()->create(['status' => Missing::$name]);
    upload($document, stageTempFile());
    $document->refresh();
    $document->status->transitionTo(Rejected::class);
    $document->update(['reviewed_by' => User::factory()->create()->id, 'rejection_reason' => 'blurry', 'reviewed_at' => now()]);

    upload($document, stageTempFile('fixed.pdf'));
    $document->refresh();

    expect($document->status)->toBeInstanceOf(Uploaded::class)
        ->and($document->rejection_reason)->toBeNull()
        ->and($document->reviewed_by)->toBeNull()
        ->and($document->reviewed_at)->toBeNull();
});

it('allows replacing an approved document, returning it to the uploaded state', function () {
    $document = ApplicationDocument::factory()->create(['status' => Missing::$name]);
    upload($document, stageTempFile());
    $document->refresh();
    $document->status->transitionTo(Approved::class);

    upload($document, stageTempFile('new.pdf'));

    expect($document->fresh()->status)->toBeInstanceOf(Uploaded::class);
});

it('rejects a disallowed mime type', function () {
    $document = ApplicationDocument::factory()->create();
    $temp = stageTempFile('note.txt', 'text/plain');

    expect(fn () => upload($document, $temp))->toThrow(DocumentUploadException::class);
    expect($document->fresh()->status)->toBeInstanceOf(Missing::class);
});

it('rejects a file over five megabytes', function () {
    $document = ApplicationDocument::factory()->create();
    $temp = stageTempFile('big.pdf');
    $temp['size'] = 6 * 1024 * 1024;

    expect(fn () => upload($document, $temp))->toThrow(DocumentUploadException::class);
});

it('rejects a temporary file that is not present on the disk', function () {
    $document = ApplicationDocument::factory()->create();

    expect(fn () => app(UploadDocumentAction::class)->execute(new UploadDocumentDTO(
        document: $document,
        temporaryPath: 'documents/tmp/missing.pdf',
        originalName: 'missing.pdf',
        mimeType: 'application/pdf',
        size: 1000,
    )))->toThrow(DocumentUploadException::class);
});

it('accepts jpeg and png uploads', function (string $name, string $mime) {
    $document = ApplicationDocument::factory()->create();

    $file = upload($document, stageTempFile($name, $mime));

    expect(Storage::disk('local')->exists($file->file_path))->toBeTrue();
})->with([
    ['photo.jpg', 'image/jpeg'],
    ['photo.png', 'image/png'],
]);

it('discards an orphaned candidate file when the write transaction fails', function () {
    $document = ApplicationDocument::factory()->create();
    // Force the transaction to fail by deleting the document row out from under the lock's
    // firstOrFail — the candidate must not be left on disk.
    $temp = stageTempFile();
    ApplicationDocument::withoutGlobalScope(BranchScope::class)->whereKey($document->id)->delete();

    expect(fn () => upload($document, $temp))->toThrow(ModelNotFoundException::class);

    // No permanent file under the document directory survives.
    expect(Storage::disk('local')->allFiles("documents/applications/{$document->application_id}/{$document->id}"))
        ->toBeEmpty();
});
