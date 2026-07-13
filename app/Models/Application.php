<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Source;
use App\Models\Scopes\BranchScope;
use App\States\Applications\Accepted;
use App\States\Applications\ApplicationState;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\Rejected;
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
