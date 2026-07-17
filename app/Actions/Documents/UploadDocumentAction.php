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
use Illuminate\Support\Facades\Log;
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

    private const TEMPORARY_DIRECTORY = 'documents/tmp/';

    /**
     * @throws DocumentUploadException
     */
    public function execute(UploadDocumentDTO $dto): ApplicationDocumentFile
    {
        $disk = Storage::disk('local');
        $temporaryPath = $this->guardTemporaryPath($dto->temporaryPath);
        $storedPath = null;

        try {
            [$mimeType, $size] = $this->inspectTemporaryFile($disk, $temporaryPath);
            $storedPath = $this->finalPath($dto->document, $mimeType);
            $this->copyToPermanentStorage($disk, $temporaryPath, $storedPath, $size);

            return DB::transaction(function () use ($dto, $storedPath, $mimeType, $size): ApplicationDocumentFile {
                $document = ApplicationDocument::withoutGlobalScope(BranchScope::class)
                    ->whereKey($dto->document->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $file = $document->files()->create([
                    'file_path' => $storedPath,
                    'original_name' => $this->safeOriginalName($dto),
                    'mime_type' => $mimeType,
                    'size' => $size,
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
            if ($storedPath !== null) {
                $this->discardCandidate($disk, $storedPath);
            }

            throw $e;
        } finally {
            $this->discardTemporaryFile($disk, $temporaryPath);
        }
    }

    /**
     * @throws DocumentUploadException
     */
    private function guardTemporaryPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $relativePath = Str::after($path, self::TEMPORARY_DIRECTORY);

        if (
            $path === ''
            || ! Str::startsWith($path, self::TEMPORARY_DIRECTORY)
            || $relativePath === ''
            || str_contains($path, "\0")
            || preg_match('#(^|/)\.{1,2}(/|$)#', $relativePath) === 1
        ) {
            throw DocumentUploadException::unexpectedPath($path, self::TEMPORARY_DIRECTORY);
        }

        return $path;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function inspectTemporaryFile(Filesystem $disk, string $path): array
    {
        if (! $disk->exists($path)) {
            throw DocumentUploadException::fileMissing($path);
        }

        $size = $disk->size($path);

        if ($size > self::MAX_BYTES) {
            throw DocumentUploadException::tooLarge($size, self::MAX_BYTES);
        }

        $mimeType = $disk->mimeType($path);

        if (! is_string($mimeType) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw DocumentUploadException::disallowedMimeType(is_string($mimeType) ? $mimeType : 'unknown');
        }

        return [$mimeType, $size];
    }

    /**
     * Copies the incoming file to its final, randomly named home under a directory unique to
     * this application and document. The path is fully server-generated, so a caller can never
     * steer the write outside the expected private tree.
     */
    private function finalPath(ApplicationDocument $document, string $mimeType): string
    {
        return $this->directoryFor($document).'/'.Str::random(40).$this->extensionFor($mimeType);
    }

    private function copyToPermanentStorage(Filesystem $disk, string $temporaryPath, string $storedPath, int $expectedSize): void
    {
        $stream = $disk->readStream($temporaryPath);

        if (! is_resource($stream)) {
            throw DocumentUploadException::storageFailure($storedPath);
        }

        try {
            $written = $disk->writeStream($storedPath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $written || ! $disk->exists($storedPath) || $disk->size($storedPath) !== $expectedSize) {
            throw DocumentUploadException::storageFailure($storedPath);
        }
    }

    private function discardCandidate(Filesystem $disk, string $path): void
    {
        $referenced = ApplicationDocumentFile::withoutGlobalScopes()
            ->where('file_path', $path)
            ->exists();

        if (! $referenced && $disk->exists($path)) {
            if (! $disk->delete($path)) {
                Log::warning('An unreferenced application document candidate could not be removed.', [
                    'file_path' => $path,
                ]);
            }
        }
    }

    private function discardTemporaryFile(Filesystem $disk, string $path): void
    {
        if ($disk->exists($path) && ! $disk->delete($path)) {
            Log::warning('A verified temporary application document upload could not be removed.', [
                'file_path' => $path,
            ]);
        }
    }

    private function safeOriginalName(UploadDocumentDTO $dto): string
    {
        $name = basename(str_replace('\\', '/', trim($dto->originalName)));

        return $name !== '' ? $name : basename($dto->temporaryPath);
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
