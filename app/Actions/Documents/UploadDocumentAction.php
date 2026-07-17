<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\DTOs\Documents\UploadDocumentDTO;
use App\Exceptions\DocumentUploadException;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\Scopes\BranchScope;
use App\States\Documents\Uploaded;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one authoritative way a document version is stored — shared by the Filament relation
 * manager and, in Phase 5, the chatbot API, so both are subject to identical guards rather
 * than each re-implementing them. Authorization is the caller's job and happens before this.
 *
 * Validation runs independently of any Filament-layer file rules (disk existence, an
 * allow-listed MIME type, the 5 MB ceiling), because the API must not be able to slip past
 * checks that only the panel enforced.
 *
 * Every upload is append-only and self-contained. It writes a *new* file row under a
 * cryptographically random name in a private directory namespaced to this application and
 * document, atomically repoints `current_file_id` at it, moves the document to
 * {@see Uploaded}, and clears any prior review verdict — so a replacement of a rejected or
 * approved document returns it to the reviewer's queue. Old rows are never rewritten; the
 * same path applies to the first upload and to every replacement.
 *
 * The document row is locked for the write so a concurrent review cannot approve the version
 * this upload is superseding. If the transaction fails after the candidate file was written,
 * the orphaned file is deleted — but only once it is certain no persisted history row
 * references it, so committed evidence can never be removed by a losing writer.
 */
final class UploadDocumentAction
{
    /**
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @throws DocumentUploadException
     */
    public function execute(UploadDocumentDTO $dto): ApplicationDocumentFile
    {
        $disk = Storage::disk('local');

        $this->guardFile($disk, $dto);

        $storedPath = $this->storeCandidate($disk, $dto);

        try {
            return DB::transaction(function () use ($dto, $storedPath): ApplicationDocumentFile {
                $document = ApplicationDocument::withoutGlobalScope(BranchScope::class)
                    ->whereKey($dto->document->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $file = $document->files()->create([
                    'file_path' => $storedPath,
                    'original_name' => $dto->originalName,
                    'mime_type' => $dto->mimeType,
                    'size' => $dto->size,
                    'uploaded_by_type' => $dto->uploadedBy?->getMorphClass(),
                    'uploaded_by_id' => $dto->uploadedBy?->getKey(),
                    'uploaded_at' => now(),
                ]);

                // Atomically supersede the previous version and wipe any review verdict so a
                // replaced document re-enters the reviewer's queue rather than keeping a stale
                // approval or rejection.
                $document->forceFill([
                    'current_file_id' => $file->getKey(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                ])->save();

                $document->status->transitionTo(Uploaded::class);

                return $file;
            }, attempts: 3);
        } catch (Throwable $e) {
            $this->discardCandidate($disk, $storedPath);

            throw $e;
        }
    }

    /**
     * @throws DocumentUploadException
     */
    private function guardFile(Filesystem $disk, UploadDocumentDTO $dto): void
    {
        if (! in_array($dto->mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw DocumentUploadException::disallowedMimeType($dto->mimeType);
        }

        if ($dto->size > self::MAX_BYTES) {
            throw DocumentUploadException::tooLarge($dto->size, self::MAX_BYTES);
        }

        if (! $disk->exists($dto->temporaryPath)) {
            throw DocumentUploadException::fileMissing($dto->temporaryPath);
        }
    }

    /**
     * Copies the incoming file to its final, randomly named home under a directory unique to
     * this application and document. The path is fully server-generated, so a caller can never
     * steer the write outside the expected private tree.
     */
    private function storeCandidate(Filesystem $disk, UploadDocumentDTO $dto): string
    {
        $directory = $this->directoryFor($dto->document);
        $path = $directory.'/'.Str::random(40).$this->extensionFor($dto->mimeType);

        $stream = $disk->readStream($dto->temporaryPath);

        try {
            $disk->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    private function discardCandidate(Filesystem $disk, string $path): void
    {
        $referenced = ApplicationDocumentFile::withoutGlobalScopes()
            ->where('file_path', $path)
            ->exists();

        if (! $referenced && $disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function directoryFor(ApplicationDocument $document): string
    {
        return sprintf(
            'documents/applications/%d/%d',
            $document->application_id,
            $document->getKey(),
        );
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => '.pdf',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            default => '',
        };
    }
}
