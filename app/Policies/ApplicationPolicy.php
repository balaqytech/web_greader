<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use Illuminate\Auth\Access\HandlesAuthorization;
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

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Application');
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
