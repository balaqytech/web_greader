<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'id_number', 'occupation', 'work_address', 'work_phone'])]
class Guardian extends Model
{
    use HasFactory;

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Get all applications linked to this guardian via the guardian contact's id_number.
     * This is a query method, not an Eloquent relationship.
     * For Filament relation managers, use a custom query instead.
     *
     * @return Builder<Application>
     */
    public function getApplicationsQuery()
    {
        return Application::whereHas('contacts', function ($query) {
            $query->where('is_guardian', true)
                ->where('id_number', $this->id_number);
        });
    }
}
