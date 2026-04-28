<?php

namespace App\Models;

use App\Enums\Gender;
use App\States\Applications\ApplicationState;
use App\States\Applications\PendingRegistration;
use App\Support\Model;
use App\Traits\HasAffiliate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'lead_id', 'season_id', 'program_id', 'branch_id', 'status',
    'student_name', 'student_gender', 'student_birth_date', 'student_civil_number',
    'student_state', 'student_governorate', 'student_village', 'student_house_number',
    'student_parents_social_status',
    'father_name', 'father_phone', 'father_email', 'father_id_number',
    'father_occupation', 'father_work_address', 'father_work_phone', 'father_is_guardian',
    'mother_name', 'mother_phone', 'mother_email', 'mother_id_number',
    'mother_occupation', 'mother_work_address', 'mother_work_phone', 'mother_is_guardian',
    'relative_name', 'relative_phone', 'relative_email', 'relative_id_number',
    'relative_occupation', 'relative_work_address', 'relative_work_phone',
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
            'student_gender' => Gender::class,
            'student_birth_date' => 'date',
            'father_is_guardian' => 'boolean',
            'mother_is_guardian' => 'boolean',
        ];
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
                $application->status = PendingRegistration::$name;
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

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }
}
