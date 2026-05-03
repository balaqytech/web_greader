<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use App\States\Applications\Accepted;
use App\States\Applications\ApplicationState;
use App\States\Applications\Cancelled;
use App\States\Applications\Draft;
use App\States\Applications\Rejected;
use App\States\Applications\Submitted;
use App\Support\Model;
use App\Traits\HasAffiliate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

#[ScopedBy(BranchScope::class)]
#[Fillable([
    'lead_id',
    'season_id',
    'program_id',
    'branch_id',
    'status',
    'rejection_reason',
])]
class Application extends Model
{
    use HasAffiliate;
    use HasFactory;
    use HasStates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationState::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $application) {
            $application->ref_no = 'APP-' . now()->format('Y') . str_pad(
                (string) (Application::withoutGlobalScopes()->count() + 1),
                6,
                '0',
                STR_PAD_LEFT
            );

            if (empty($application->status)) {
                $application->status = Draft::$name;
            }
        });
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ApplicationActivity::class)->latest('transitioned_at');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function applicationStudent(): HasOne
    {
        return $this->hasOne(ApplicationStudent::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ApplicationContact::class);
    }

    public function guardianContact(): HasOne
    {
        // Always eager-load this via ->with('guardianContact') to avoid N+1.
        return $this->hasOne(ApplicationContact::class)
            ->where('is_guardian', true);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(ApplicationContract::class);
    }

    /**
     * Check if a valid (non-expired) contract token exists via the contract relationship.
     */
    public function hasValidContractToken(): bool
    {
        return $this->contract !== null
            && $this->contract->token !== null
            && $this->contract->token_expires_at !== null
            && $this->contract->token_expires_at->isFuture();
    }

    /**
     * Check if a signed contract file exists via the contract relationship.
     */
    public function hasValidContractFile(): bool
    {
        return $this->contract !== null
            && $this->contract->file_path !== null;
    }

    /**
     * Timestamp accessors derived from ApplicationActivity records.
     * These replace the removed submitted_at, accepted_at, rejected_at, cancelled_at columns.
     */
    public function getSubmittedAtAttribute(): ?Carbon
    {
        return $this->activities()
            ->where('to_state', Submitted::getMorphClass())
            ->oldest('transitioned_at')
            ->value('transitioned_at');
    }

    public function getAcceptedAtAttribute(): ?Carbon
    {
        return $this->activities()
            ->where('to_state', Accepted::getMorphClass())
            ->value('transitioned_at');
    }

    public function getRejectedAtAttribute(): ?Carbon
    {
        return $this->activities()
            ->where('to_state', Rejected::getMorphClass())
            ->value('transitioned_at');
    }

    public function getCancelledAtAttribute(): ?Carbon
    {
        return $this->activities()
            ->where('to_state', Cancelled::getMorphClass())
            ->value('transitioned_at');
    }
}
