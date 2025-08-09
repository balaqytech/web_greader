<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AcademicYear;
use Illuminate\Auth\Access\HandlesAuthorization;

class AcademicYearPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('view_academic::year');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_academic::year');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('update_academic::year');
    }
}
