<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Application;
use App\Models\Branch;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User as AuthUser;

class ApplicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Application');
    }

    public function view(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('View:Application');
    }

    /**
     * $branch is only supplied by callers that already know which branch the application is
     * being created in (e.g. manual entry) — the Filament resource's coarse create-page gate
     * has no record yet, so it authorizes without it.
     */
    public function create(AuthUser $authUser, ?Branch $branch = null): Response|bool
    {
        if (! $authUser->can('Create:Application')) {
            return false;
        }

        if ($branch === null) {
            return true;
        }

        return $this->canActInBranch($authUser, $branch)
            ? true
            : Response::deny(__('exceptions.branch_not_authorized', ['branch' => $branch->name]));
    }

    /**
     * Central (branchless) users and `super_admin` may create in any branch; a branch-scoped
     * employee may only create within their own branch, regardless of what a tampered request
     * claims.
     */
    private function canActInBranch(AuthUser $authUser, Branch $branch): bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }

        if ($authUser->branch_id === null) {
            return true;
        }

        return $authUser->branch_id === $branch->id;
    }

    public function update(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Update:Application')
            && $this->isEditableState($application);
    }

    /**
     * Application data is only editable while it is still being assembled. Once a contract
     * has been generated for signature, and through review/terminal states, the record is
     * immutable here until correction/versioning exists — enforced in authorization, not
     * merely by hiding the action.
     */
    private function isEditableState(Application $application): bool
    {
        return $application->status instanceof AwaitingRegistrationFee
            || $application->status instanceof AwaitingApplicationCompletion;
    }

    public function delete(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Delete:Application');
    }
}
