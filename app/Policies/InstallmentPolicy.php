<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Installment;
use App\Enums\InstallmentStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstallmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_installment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Installment $installment): bool
    {
        return $user->can('view_installment');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Installment $installment): bool
    {
        return $user->can('update_installment') && $installment->status !== InstallmentStatus::PAID;
    }
}
