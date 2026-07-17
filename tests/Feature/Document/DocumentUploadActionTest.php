<?php

declare(strict_types=1);

use App\Actions\Documents\ApproveDocumentAction;
use App\Actions\Documents\RejectDocumentAction;
use App\Actions\Documents\UploadDocumentAction;
use App\DTOs\Documents\UploadDocumentDTO;
use App\Exceptions\DocumentUploadException;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\States\Documents\Missing;
use App\States\Documents\Uploaded;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function stageTempFile(string $name = 'doc.pdf', string $mime = 'application/pdf', int $kilobytes = 100): array
{
    $path = 'documents/tmp/'.$name;
    $header = match ($mime) {
        'application/pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n",
        'image/jpeg' => "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9",
        'image/png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZK1sAAAAASUVORK5CYII=', true),
        default => 'plain text',
    };
    $contents = str_pad((string) $header, $kilobytes * 1024, "\0");

    Storage::disk('local')->put($path, $contents);

    return [
        'path' => $path,
        'name' => $name,
    ];
}

function upload(ApplicationDocument $document, array $temp, ?User $by = null): ApplicationDocumentFile
{
    return app(UploadDocumentAction::class)->execute(new UploadDocumentDTO(
        document: $document,
        temporaryPath: $temp['path'],
        originalName: $temp['name'],
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
        ->and($file->size)->toBe(100 * 1024)
        ->and($file->uploaded_by_id)->toBe($uploader->id)
        ->and(Storage::disk('local')->exists($file->file_path))->toBeTrue()
        ->and(Storage::disk('local')->exists('documents/tmp/doc.pdf'))->toBeFalse();
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
    app(RejectDocumentAction::class)->execute($document, User::factory()->create(), 'blurry');

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
    app(ApproveDocumentAction::class)->execute($document, User::factory()->create());

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
    $temp = stageTempFile('big.pdf', kilobytes: 6 * 1024);

    expect(fn () => upload($document, $temp))->toThrow(DocumentUploadException::class);
    expect(Storage::disk('local')->exists($temp['path']))->toBeFalse();
});

it('rejects a temporary file that is not present on the disk', function () {
    $document = ApplicationDocument::factory()->create();

    expect(fn () => app(UploadDocumentAction::class)->execute(new UploadDocumentDTO(
        document: $document,
        temporaryPath: 'documents/tmp/missing.pdf',
        originalName: 'missing.pdf',
    )))->toThrow(DocumentUploadException::class);
});

it('rejects paths outside the owned temporary directory without deleting them', function (string $path) {
    $document = ApplicationDocument::factory()->create();
    Storage::disk('local')->put('contracts/winner.pdf', "%PDF-1.4\nprotected");

    expect(fn () => app(UploadDocumentAction::class)->execute(new UploadDocumentDTO(
        document: $document,
        temporaryPath: $path,
        originalName: 'winner.pdf',
    )))->toThrow(DocumentUploadException::class);

    expect(Storage::disk('local')->exists('contracts/winner.pdf'))->toBeTrue()
        ->and($document->fresh()->status)->toBeInstanceOf(Missing::class);
})->with([
    'unrelated private path' => 'contracts/winner.pdf',
    'temporary directory traversal' => 'documents/tmp/../contracts/winner.pdf',
]);

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

it('treats a false storage write as a failed upload with no persisted history', function () {
    $document = ApplicationDocument::factory()->create();
    $temporaryPath = 'documents/tmp/write-failure.pdf';
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "%PDF-1.4\nwrite failure");
    rewind($stream);

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('exists')->andReturnUsing(
        fn (string $path): bool => $path === $temporaryPath,
    );
    $disk->shouldReceive('size')->with($temporaryPath)->andReturn(24);
    $disk->shouldReceive('mimeType')->with($temporaryPath)->andReturn('application/pdf');
    $disk->shouldReceive('readStream')->with($temporaryPath)->andReturn($stream);
    $disk->shouldReceive('writeStream')->once()->andReturn(false);
    $disk->shouldReceive('delete')->once()->with($temporaryPath)->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    expect(fn () => app(UploadDocumentAction::class)->execute(new UploadDocumentDTO(
        document: $document,
        temporaryPath: $temporaryPath,
        originalName: 'write-failure.pdf',
    )))->toThrow(DocumentUploadException::class);

    expect($document->fresh()->status)->toBeInstanceOf(Missing::class)
        ->and($document->files()->count())->toBe(0)
        ->and($document->fresh()->current_file_id)->toBeNull();
});
