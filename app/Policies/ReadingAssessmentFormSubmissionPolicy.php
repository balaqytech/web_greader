<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReadingAssessmentFormSubmission;
use App\Support\Authorization\BranchAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReadingAssessmentFormSubmissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReadingAssessmentFormSubmission');
    }

    public function view(AuthUser $authUser, ReadingAssessmentFormSubmission $readingAssessmentFormSubmission): bool
    {
        return $authUser->can('View:ReadingAssessmentFormSubmission')
            && BranchAccess::canAccessBranch($authUser, ReadingAssessmentFormSubmission::class, $readingAssessmentFormSubmission->branch_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReadingAssessmentFormSubmission');
    }

    public function update(AuthUser $authUser, ReadingAssessmentFormSubmission $readingAssessmentFormSubmission): bool
    {
        return $authUser->can('Update:ReadingAssessmentFormSubmission')
            && BranchAccess::canAccessBranch($authUser, ReadingAssessmentFormSubmission::class, $readingAssessmentFormSubmission->branch_id);
    }

    public function delete(AuthUser $authUser, ReadingAssessmentFormSubmission $readingAssessmentFormSubmission): bool
    {
        return $authUser->can('Delete:ReadingAssessmentFormSubmission')
            && BranchAccess::canAccessBranch($authUser, ReadingAssessmentFormSubmission::class, $readingAssessmentFormSubmission->branch_id);
    }
}
