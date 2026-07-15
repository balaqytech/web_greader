<?php

namespace App\Models\Scopes;

use App\Support\Authorization\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts every query for a branch-owned model to the acting user's own branch, unless
 * they have global (`super_admin`) or model-specific (`ViewAllBranches:{Model}`) cross-branch
 * access — see App\Support\Authorization\BranchAccess, the single shared implementation this
 * scope and every branch-scoped model's policy both defer to, so the two can never diverge.
 *
 * Unauthenticated/system contexts (console commands, queued jobs with no acting user) are
 * left unscoped, matching prior behavior. An authenticated user with neither cross-branch
 * access nor a branch_id now sees no records at all: previously this combination silently
 * skipped scoping entirely and granted full cross-branch visibility, a tenancy hole.
 */
class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        if (BranchAccess::canSeeAllBranches($user, $model::class)) {
            return;
        }

        if ($user->branch_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Table-qualified so this scope never produces an ambiguous "branch_id" reference
        // once the query joins in another table that also has a branch_id column.
        $builder->where($model->getTable().'.branch_id', $user->branch_id);
    }
}
