<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Application;
use App\Models\Branch;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\CorrectionRequested;
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
        return $this->authorizeForBranch($authUser, $application, 'Update:Application')
            && $this->isEditableState($application);
    }

    /**
     * Application data is editable while it is still being assembled (registration-fee and
     * data-completion stages) and, additionally, while an open correction is being worked —
     * `CorrectionRequested` with an open correction row lets staff fix the flagged data before
     * completing the correction. Once signed and through review/terminal states with no open
     * correction, the record is immutable here — enforced in authorization, not merely by
     * hiding the action.
     */
    private function isEditableState(Application $application): bool
    {
        if ($application->status instanceof AwaitingRegistrationFee
            || $application->status instanceof AwaitingApplicationCompletion) {
            return true;
        }

        return $application->status instanceof CorrectionRequested
            && $application->openCorrection()->exists();
    }

    public function delete(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'Delete:Application');
    }

    public function generateContract(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'GenerateContract:Application');
    }

    public function uploadSignedContract(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'UploadSignedContract:Application');
    }

    /**
     * Reopens signature-stage data entry (AwaitingContractSignature back to
     * AwaitingApplicationCompletion) — gated by the same `Update:Application` permission as
     * ordinary edits, since it's a data-correction action rather than a distinct workflow
     * step of its own.
     */
    public function reopen(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'Update:Application');
    }

    public function requestCorrection(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'RequestCorrection:Application');
    }

    public function completeCorrection(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'CompleteCorrection:Application');
    }

    public function accept(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'Accept:Application');
    }

    public function reject(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'Reject:Application');
    }

    public function cancel(AuthUser $authUser, Application $application): bool
    {
        return $this->authorizeForBranch($authUser, $application, 'Cancel:Application');
    }

    private function authorizeForBranch(AuthUser $authUser, Application $application, string $permission): bool
    {
        return $authUser->can($permission)
            && BranchAccess::canAccessBranch($authUser, Application::class, $application->branch_id);
    }
}
