<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Guardian;
use Illuminate\Auth\Access\HandlesAuthorization;

class GuardianPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Guardian');
    }

    public function view(AuthUser $authUser, Guardian $guardian): bool
    {
        return $authUser->can('View:Guardian');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Guardian');
    }

    public function update(AuthUser $authUser, Guardian $guardian): bool
    {
        return $authUser->can('Update:Guardian');
    }

    public function delete(AuthUser $authUser, Guardian $guardian): bool
    {
        return $authUser->can('Delete:Guardian');
    }

}