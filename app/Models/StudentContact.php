<?php

namespace App\Models;

use App\Enums\ContactType;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'type',
    'relationship',
    'name',
    'phone',
    'email',
    'id_number',
    'occupation',
    'work_address',
    'work_phone',
    'is_guardian',
])]
class StudentContact extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'is_guardian' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
