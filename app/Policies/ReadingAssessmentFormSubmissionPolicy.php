<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReadingAssessmentFormSubmission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReadingAssessmentFormSubmissionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReadingAssessmentFormSubmission');
    }

    public function view(AuthUser $authUser, ReadingAssessmentFormSubmission $readingAssessmentFormSubmission): bool
    {
        return $authUser->can('View:ReadingAssessmentFormSubmission');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReadingAssessmentFormSubmission');
    }

    public function update(AuthUser $authUser, ReadingAssessmentFormSubmission $readingAssessmentFormSubmission): bool
    {
        return $authUser->can('Update:ReadingAssessmentFormSubmission');
    }

    public function delete(AuthUser $authUser, ReadingAssessmentFormSubmission $readingAssessmentFormSubmission): bool
    {
        return $authUser->can('Delete:ReadingAssessmentFormSubmission');
    }

}