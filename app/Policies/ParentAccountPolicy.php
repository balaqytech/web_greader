<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ParentAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class ParentAccountPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_parent::account');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ParentAccount $parentAccount): bool
    {
        return $user->can('view_parent::account');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_parent::account');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ParentAccount $parentAccount): bool
    {
        return $user->can('update_parent::account');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ParentAccount $parentAccount): bool
    {
        return false;
    }
}
