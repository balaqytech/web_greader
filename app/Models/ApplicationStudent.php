<?php

namespace App\Models;

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
    'parents_social_status',
])]
class ApplicationStudent extends Model
{
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
