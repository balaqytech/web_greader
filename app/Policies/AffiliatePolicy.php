<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class AffiliatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Affiliate');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Affiliate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Affiliate');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Affiliate');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Affiliate');
    }

}