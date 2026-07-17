<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApplicationDocument;
use App\Support\Authorization\BranchAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ApplicationDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ApplicationDocument');
    }

    public function view(AuthUser $authUser, ApplicationDocument $document): bool
    {
        return $authUser->can('View:ApplicationDocument')
            && BranchAccess::canAccessBranch($authUser, ApplicationDocument::class, $document->branch_id);
    }

    public function upload(AuthUser $authUser, ApplicationDocument $document): bool
    {
        return $authUser->can('Upload:ApplicationDocument')
            && BranchAccess::canAccessBranch($authUser, ApplicationDocument::class, $document->branch_id);
    }

    public function review(AuthUser $authUser, ApplicationDocument $document): bool
    {
        return $authUser->can('Review:ApplicationDocument')
            && BranchAccess::canAccessBranch($authUser, ApplicationDocument::class, $document->branch_id);
    }
}
