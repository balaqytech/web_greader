<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Application;
use App\Models\Branch;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\Support\Authorization\BranchAccess;
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
        return $authUser->can('View:Application')
            && BranchAccess::canAccessBranch($authUser, Application::class, $application->branch_id);
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

        return BranchAccess::canAccessBranch($authUser, Application::class, $branch->id)
            ? true
            : Response::deny(__('exceptions.branch_not_authorized', ['branch' => $branch->name]));
    }

    public function update(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Update:Application')
            && BranchAccess::canAccessBranch($authUser, Application::class, $application->branch_id)
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
        return $authUser->can('Delete:Application')
            && BranchAccess::canAccessBranch($authUser, Application::class, $application->branch_id);
    }
}
