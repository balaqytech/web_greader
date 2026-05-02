<?php

namespace App\Models;

use App\Enums\Gender;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(
            Application::class,
            ApplicationStudent::class,
            'civil_number', // Foreign key on ApplicationStudent
            'id',           // Foreign key on Application
            'civil_number', // Local key on Student
            'application_id' // Local key on ApplicationStudent
        );
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
