<?php

namespace App\Models;

use App\Enums\Gender;
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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'lead_id',
    'student_id',
    'season_id',
    'program_id',
    'branch_id',
    'status',
    'rejection_reason',
    'student_name',
    'student_gender',
    'student_birth_date',
    'student_civil_number',
    'student_state',
    'student_governorate',
    'student_village',
    'student_house_number',
    'student_parents_social_status',
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
    'contract_token',
    'contract_token_expires_at',
    'contract_signed_at',
    'contract_signed_by_applicant',
    'contract_file_path',
    'contract_signature_path',
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
            'student_gender' => Gender::class,
            'student_birth_date' => 'date',
            'father_is_guardian' => 'boolean',
            'mother_is_guardian' => 'boolean',
            'contract_token_expires_at' => 'datetime',
            'contract_signed_at' => 'datetime',
            'contract_signed_by_applicant' => 'boolean',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hasValidContractToken(): bool
    {
        return $this->contract_token !== null
            && $this->contract_token_expires_at !== null
            && $this->contract_token_expires_at->isFuture();
    }

    public function hasValidContractFile(): bool
    {
        return $this->contract_file_path !== null
            && Storage::disk('public')->exists($this->contract_file_path);
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
