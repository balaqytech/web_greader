<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Source;
use App\Models\Scopes\BranchScope;
use App\States\Applications\Accepted;
use App\States\Applications\ApplicationState;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\Rejected;
use App\States\Contracts\Generated;
use App\States\Contracts\Signed;
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
    'student_id',
    'season_id',
    'program_id',
    'branch_id',
    'status',
    'rejection_reason',
    'source',
    'student_name',
    'student_gender',
    'student_birth_date',
    'student_civil_number',
    'student_state',
    'student_governorate',
    'student_village',
    'student_house_number',
    'student_parents_social_status',
    'relationship_with_guardian',
    'father_name',
    'father_phone',
    'father_email',
    'father_id_number',
    'father_occupation',
    'father_work_address',
    'father_work_phone',
    'father_is_guardian',
    'mother_name',
    'mother_phone',
    'mother_email',
    'mother_id_number',
    'mother_occupation',
    'mother_work_address',
    'mother_work_phone',
    'mother_is_guardian',
    'relative_name',
    'relative_phone',
    'relative_email',
    'relative_id_number',
    'relative_occupation',
    'relative_work_address',
    'relative_work_phone',
    'is_transfer_student',
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
            'student_gender' => Gender::class,
            'status' => ApplicationState::class,
            'source' => Source::class,
            'student_birth_date' => 'date',
            'father_is_guardian' => 'boolean',
            'mother_is_guardian' => 'boolean',
            'is_transfer_student' => 'boolean',
            'relationship_with_guardian' => GuardianRelationship::class,
        ];
    }

    public function getGuardianNameAttribute(): ?string
    {
        return $this->father_is_guardian
            ? $this->father_name
            : ($this->mother_is_guardian
                ? $this->mother_name
                : $this->relative_name);
    }

    public function getGuardianPhoneAttribute(): ?string
    {
        return $this->father_is_guardian
            ? $this->father_phone
            : ($this->mother_is_guardian
                ? $this->mother_phone
                : $this->relative_phone);
    }

    public function getGuardianIdNumberAttribute(): ?string
    {
        return $this->father_is_guardian
            ? $this->father_id_number
            : ($this->mother_is_guardian
                ? $this->mother_id_number
                : $this->relative_id_number);
    }

    protected static function booted(): void
    {
        static::creating(function (self $application) {
            $application->ref_no = 'APP-'.now()->format('Y').str_pad(
                (string) (Application::withoutGlobalScopes()->count() + 1),
                6,
                '0',
                STR_PAD_LEFT
            );

            if (empty($application->status)) {
                $application->status = AwaitingRegistrationFee::$name;
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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

    /**
     * All contract versions, newest first. The single-contract relation was retired with
     * versioning (§5.5): production reads either the whole history (`contracts()`) or the one
     * live version (`activeContract()`), never an ambiguous "the contract".
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(ApplicationContract::class)->orderByDesc('version');
    }

    /**
     * The one live version — the highest-version contract still `generated` or `signed`. The
     * generation action supersedes any prior active version under lock before creating the
     * next, so at most one row ever matches; `latest('version')` is defensive ordering, not a
     * tie-break that should ever be needed. Callers under a row lock should
     * `->activeContract()->lockForUpdate()->first()` so they act on the committed live version.
     */
    public function activeContract(): HasOne
    {
        return $this->hasOne(ApplicationContract::class)
            ->whereIn('status', [Generated::$name, Signed::$name])
            ->latest('version');
    }

    /**
     * TEMPORARY test-only shim, retired in the acceptance/matrix hardening commit (§ commit 15).
     * Returns the highest-version contract regardless of status so pre-versioning tests that
     * reach for "the contract" keep resolving the current row. Production code must not use this
     * — it reads `activeContract()` (the one live version) or `contracts()` (the full history).
     */
    public function contract(): HasOne
    {
        return $this->hasOne(ApplicationContract::class)->latest('version');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(ApplicationCorrection::class)->latest('requested_at');
    }

    /**
     * The single open correction, if any. At most one is ever open at a time — enforced by
     * locking the application row in the request/complete actions.
     */
    public function openCorrection(): HasOne
    {
        return $this->hasOne(ApplicationCorrection::class)->whereNull('completed_at');
    }

    /**
     * The single authoritative rule for whether this application's contract may be rendered
     * or signed: the application must be persisted in AwaitingContractSignature, the contract
     * must carry a non-null token and a non-null, strictly-future expiry, and the contract
     * must not already be signed off. Reused by the public controller (GET/POST) and the
     * signing action so all three agree; callers under a row lock should pass the freshly
     * locked contract explicitly rather than relying on a possibly-stale loaded relation.
     *
     * Pass $expectedToken when binding a specific request to a specific contract (e.g. while
     * signing): a token invalidated by a reopen/regenerate cycle in between must not match
     * the replacement contract's new token, even though the replacement is otherwise signable.
     */
    public function hasSignableContract(?ApplicationContract $contract = null, ?string $expectedToken = null): bool
    {
        $contract ??= $this->activeContract;

        return $this->status instanceof AwaitingContractSignature
            && $contract !== null
            && $contract->token !== null
            && $contract->token_expires_at !== null
            && $contract->token_expires_at->isFuture()
            && ! $contract->isSignedOff()
            && ($expectedToken === null || $contract->token === $expectedToken);
    }

    /**
     * Timestamp accessors derived from ApplicationActivity records.
     * These replace the removed submitted_at, accepted_at, rejected_at, cancelled_at columns.
     */
    public function getSubmittedAtAttribute(): ?Carbon
    {
        return $this->activities()
            ->where('to_state', AwaitingApplicationCompletion::getMorphClass())
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
