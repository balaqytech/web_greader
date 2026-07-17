<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Actions\Documents\UploadDocumentAction;
use App\Models\ApplicationDocument;
use Illuminate\Database\Eloquent\Model;

/**
 * Input to {@see UploadDocumentAction}. The incoming file has already
 * been written to the private `local` disk (by Filament, or in Phase 5 by the API); this DTO
 * carries its temporary path plus the metadata the action records and validates. Kept free of
 * any HTTP type so the same action serves the panel and a future REST endpoint.
 */
final class UploadDocumentDTO
{
    public function __construct(
        public readonly ApplicationDocument $document,
        public readonly string $temporaryPath,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?Model $uploadedBy = null,
    ) {}
}
