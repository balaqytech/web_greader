<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Addon;
use Illuminate\Auth\Access\HandlesAuthorization;

class AddonPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_addon');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Addon $addon): bool
    {
        return $user->can('view_addon');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_addon');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Addon $addon): bool
    {
        return $user->can('update_addon');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Addon $addon): bool
    {
        return false;
    }
}
