<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\Scopes\BranchScope;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy(BranchScope::class)]
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
    'relationship_with_guardian',
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
            'relationship_with_guardian' => GuardianRelationship::class,
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
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
