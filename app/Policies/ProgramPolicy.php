<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Program;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProgramPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Program');
    }

    public function view(AuthUser $authUser, Program $program): bool
    {
        return $authUser->can('View:Program');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Program');
    }

    public function update(AuthUser $authUser, Program $program): bool
    {
        return $authUser->can('Update:Program');
    }

    public function delete(AuthUser $authUser, Program $program): bool
    {
        return $authUser->can('Delete:Program');
    }

}