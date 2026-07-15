<?php

namespace App\Models\Scopes;

use App\Models\Affiliate;
use App\Models\Application;
use App\Models\Lead;
use App\Models\User;
use App\Support\Authorization\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts every query for a branch-owned model to the acting principal's own records,
 * with the exact rule depending on who is authenticated:
 *
 *   - Unauthenticated/system contexts (console commands, queued jobs with no acting user)
 *     are left unscoped, matching prior behavior.
 *   - `App\Models\User` (the operational Shield user): the existing branch rules apply —
 *     global (`super_admin`) or model-specific (`ViewAllBranches:{Model}`) cross-branch
 *     access sees every branch; everyone else only their own `branch_id`; a user with
 *     neither cross-branch access nor a `branch_id` sees no records at all.
 *   - `App\Models\Affiliate` (the `affiliate` guard's principal — has no Shield roles/
 *     permissions, so it can never go through BranchAccess without fataling on
 *     hasRole()/can()): `Lead` and `Application` are constrained directly to
 *     `<table>.affiliate_id = $affiliate->id` — enforced by this scope itself, not left to
 *     whatever `where` clause a relation like `Affiliate::leads()` happens to add, so a
 *     direct `Lead::query()` while authenticated as an affiliate is just as safe as going
 *     through the relation. Every other branch-scoped model (no `affiliate_id` ownership
 *     concept) returns no records for an affiliate.
 *   - Any other authenticated principal this scope doesn't recognize fails closed (no
 *     records) rather than risk silently exposing every branch's data to a guard nobody
 *     has reasoned about yet.
 *
 * See App\Support\Authorization\BranchAccess for the User-specific rule — the single shared
 * implementation this scope and every branch-scoped model's policy both defer to, so the two
 * can never diverge on that half of the logic.
 */
class BranchScope implements Scope
{
    /**
     * Branch-scoped models an authenticated Affiliate may see, constrained to their own
     * affiliate_id. Every other branch-scoped model has no affiliate_id ownership concept
     * and returns no records for an affiliate.
     *
     * @var list<class-string<Model>>
     */
    private const AFFILIATE_OWNED_MODELS = [Lead::class, Application::class];

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        if ($user instanceof User) {
            $this->applyForStaffUser($builder, $model, $user);

            return;
        }

        if ($user instanceof Affiliate) {
            $this->applyForAffiliate($builder, $model, $user);

            return;
        }

        // An authenticated principal this scope has no rule for — fail closed.
        $builder->whereRaw('1 = 0');
    }

    private function applyForStaffUser(Builder $builder, Model $model, User $user): void
    {
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

    private function applyForAffiliate(Builder $builder, Model $model, Affiliate $affiliate): void
    {
        if (! in_array($model::class, self::AFFILIATE_OWNED_MODELS, true)) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.affiliate_id', $affiliate->id);
    }
}
