<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Season;
use Illuminate\Auth\Access\HandlesAuthorization;

class SeasonPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Season');
    }

    public function view(AuthUser $authUser, Season $season): bool
    {
        return $authUser->can('View:Season');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Season');
    }

    public function update(AuthUser $authUser, Season $season): bool
    {
        return $authUser->can('Update:Season');
    }

    public function close(AuthUser $authUser, Season $season): bool
    {
        return $authUser->can('Close:Season');
    }

    public function open(AuthUser $authUser, Season $season): bool
    {
        return $authUser->can('Open:Season');
    }
}
