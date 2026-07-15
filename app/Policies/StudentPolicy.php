<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Student;
use App\Support\Authorization\BranchAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StudentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Student');
    }

    public function view(AuthUser $authUser, Student $student): bool
    {
        return $authUser->can('View:Student')
            && BranchAccess::canAccessBranch($authUser, Student::class, $student->branch_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Student');
    }

    public function update(AuthUser $authUser, Student $student): bool
    {
        return $authUser->can('Update:Student')
            && BranchAccess::canAccessBranch($authUser, Student::class, $student->branch_id);
    }

    public function delete(AuthUser $authUser, Student $student): bool
    {
        return $authUser->can('Delete:Student')
            && BranchAccess::canAccessBranch($authUser, Student::class, $student->branch_id);
    }
}
