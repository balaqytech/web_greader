<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * A correction request raised during branch review (§5.6). Reachable only through its
 * application, so it carries no BranchScope of its own — the ApplicationPolicy verifies branch
 * ownership via `$correction->application->branch_id`.
 *
 * Corrections are part of the admission record: they are never generically deleted, and an
 * already-completed correction is immutable (the completing write itself is allowed — that is
 * the one update that moves a row from open to completed). Both rules are enforced with model
 * events so no code path can bypass them.
 *
 * @property array<int, array{item: string, done: bool}> $checklist
 */
#[Fillable([
    'application_id',
    'requested_by',
    'reason',
    'checklist',
    'data_before',
    'is_contract_relevant',
    'requested_at',
    'completed_by',
    'completed_at',
])]
class ApplicationCorrection extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'data_before' => 'array',
            'is_contract_relevant' => 'boolean',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $correction) {
            if ($correction->getOriginal('completed_at') !== null) {
                throw new RuntimeException('A completed correction is immutable and cannot be mutated.');
            }
        });

        static::deleting(function () {
            throw new RuntimeException('Corrections are part of the admission record and cannot be deleted.');
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isOpen(): bool
    {
        return $this->completed_at === null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
