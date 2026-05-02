<?php

namespace App\Models;

use App\Enums\Gender;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'guardian_id',
    'branch_id',
    'name',
    'gender',
    'birth_date',
    'civil_number',
    'state',
    'governorate',
    'village',
    'house_number',
    'parents_social_status',
    'father_data',
    'mother_data',
    'relative_data',
])]
class Student extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'father_data' => 'array',
            'mother_data' => 'array',
            'relative_data' => 'array',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(StudentContact::class);
    }
}
