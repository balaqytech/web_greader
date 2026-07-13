<?php

namespace App\Models;

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
     * Get all applications on which this guardian (matched by id_number) is the acting
     * guardian, resolved from the flat father/mother/relative columns. This is a query
     * method, not an Eloquent relationship; for Filament relation managers, use this query.
     *
     * @return Builder<Application>
     */
    public function getApplicationsQuery()
    {
        // Grouped so any later global scope (e.g. BranchScope in Phase 1) ANDs with the
        // whole guardian-match group rather than only the last OR branch.
        return Application::query()->where(function (Builder $query) {
            $query->where(function (Builder $sub) {
                $sub->where('father_is_guardian', true)
                    ->where('father_id_number', $this->id_number);
            })->orWhere(function (Builder $sub) {
                $sub->where('father_is_guardian', false)
                    ->where('mother_is_guardian', true)
                    ->where('mother_id_number', $this->id_number);
            })->orWhere(function (Builder $sub) {
                $sub->where('father_is_guardian', false)
                    ->where('mother_is_guardian', false)
                    ->where('relative_id_number', $this->id_number);
            });
        });
    }
}
