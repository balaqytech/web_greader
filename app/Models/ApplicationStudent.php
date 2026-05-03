<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id',
    'name',
    'gender',
    'birth_date',
    'civil_number',
    'state',
    'governorate',
    'village',
    'house_number',
    'relationship_with_guardian',
    'parents_social_status',
])]
class ApplicationStudent extends Model
{
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'relationship_with_guardian' => GuardianRelationship::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
