<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * A document upload was rejected by the authoritative upload action's own validation, which
 * runs independently of any Filament-layer file validation so the API in Phase 5 is held to
 * the same rules. Each factory carries the translation key its message should surface to the
 * user, keeping the HTTP-agnostic action free of presentation concerns.
 */
class DocumentUploadException extends Exception
{
    private function __construct(
        string $message,
        public readonly string $translationKey,
    ) {
        parent::__construct($message);
    }

    public static function fileMissing(string $path): self
    {
        return new self(
            sprintf('Uploaded file [%s] is not present on the private disk.', $path),
            'admin.document.messages.upload_failed',
        );
    }

    public static function unexpectedPath(string $path, string $expectedPrefix): self
    {
        return new self(
            sprintf('Uploaded file [%s] is outside the expected private directory [%s].', $path, $expectedPrefix),
            'admin.document.messages.upload_failed',
        );
    }

    public static function disallowedMimeType(string $mimeType): self
    {
        return new self(
            sprintf('MIME type [%s] is not an allowed document type.', $mimeType),
            'admin.document.messages.invalid_file_type',
        );
    }

    public static function tooLarge(int $size, int $maxBytes): self
    {
        return new self(
            sprintf('Uploaded file is %d bytes, larger than the %d byte limit.', $size, $maxBytes),
            'admin.document.messages.file_too_large',
        );
    }

    public static function storageFailure(string $path): self
    {
        return new self(
            sprintf('Uploaded file [%s] could not be persisted on the private disk.', $path),
            'admin.document.messages.upload_failed',
        );
    }

    public static function currentFileRequired(int $documentId): self
    {
        return new self(
            sprintf('Document %d cannot enter uploaded state without a persisted current file.', $documentId),
            'admin.document.messages.upload_failed',
        );
    }

    public static function staleReviewMetadata(int $documentId): self
    {
        return new self(
            sprintf('Document %d cannot enter uploaded state while stale review metadata remains.', $documentId),
            'admin.document.messages.upload_failed',
        );
    }
}
