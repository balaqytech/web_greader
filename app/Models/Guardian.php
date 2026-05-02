<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'id_number', 'occupation', 'work_address', 'work_phone', 'relationship'])]
class Guardian extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(
            Application::class,
            Student::class,
            'guardian_id',
            'student_id',
            'id',
            'id'
        );
    }
}
