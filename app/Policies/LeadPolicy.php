<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lead;
use App\Support\Authorization\BranchAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeadPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Lead');
    }

    public function view(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('View:Lead')
            && BranchAccess::canAccessBranch($authUser, Lead::class, $lead->branch_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Lead');
    }

    public function update(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('Update:Lead')
            && BranchAccess::canAccessBranch($authUser, Lead::class, $lead->branch_id);
    }

    public function delete(AuthUser $authUser, Lead $lead): bool
    {
        return $authUser->can('Delete:Lead')
            && BranchAccess::canAccessBranch($authUser, Lead::class, $lead->branch_id);
    }
}
