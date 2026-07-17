<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Scopes\BranchScope;
use App\States\Documents\DocumentState;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

/**
 * One per-application document requirement. Rows are created by SyncRequiredDocumentsAction
 * when the application enters data completion, one per DocumentType, unique per
 * (application_id, type) — never deleted, only re-flagged (a transfer file for a student no
 * longer marked as transferring stays, with its history, as optional).
 *
 * `branch_id` is strictly denormalized from the owning application at creation so
 * BranchScope and record-level policy checks never need a join; no code path may repoint
 * it independently of the application.
 *
 * Auditing comes from the base model: review decisions are part of the admission record.
 *
 * @property DocumentType $type
 * @property DocumentState $status
 */
#[ScopedBy(BranchScope::class)]
#[Fillable([
    'application_id',
    'branch_id',
    'type',
    'status',
    'is_required',
    'requirement_group',
    'current_file_id',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
])]
class ApplicationDocument extends Model
{
    use HasFactory;
    use HasStates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentState::class,
            'is_required' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currentFile(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocumentFile::class, 'current_file_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ApplicationDocumentFile::class)->latest('id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
