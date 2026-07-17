<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One uploaded file version for a document requirement. Rows are append-only evidence: a
 * replacement upload creates a new row and repoints the document's current_file_id, it
 * never rewrites an existing row. Any attempt to update or delete a persisted row through
 * the application throws — the only sanctioned removal is the database-level cascade when
 * the owning document (and application) is removed.
 *
 * Auditing comes from the base model.
 */
#[Fillable([
    'application_document_id',
    'file_path',
    'original_name',
    'mime_type',
    'size',
    'uploaded_by_type',
    'uploaded_by_id',
    'uploaded_at',
])]
class ApplicationDocumentFile extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $file): void {
            throw new LogicException('Application document file history rows are append-only and cannot be updated.');
        });

        static::deleting(function (self $file): void {
            throw new LogicException('Application document file history rows are append-only and cannot be deleted.');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'application_document_id');
    }

    public function uploadedBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'uploaded_by_type', 'uploaded_by_id');
    }
}
