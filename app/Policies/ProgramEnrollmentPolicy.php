<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProgramEnrollment;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProgramEnrollmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_program::enrollment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProgramEnrollment $programEnrollment): bool
    {
        return $user->can('view_program::enrollment');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_program::enrollment');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProgramEnrollment $programEnrollment): bool
    {
        return $user->can('update_program::enrollment');
    }
}
