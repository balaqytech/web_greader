<?php

namespace App\Support\Authorization;

use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Single source of truth for branch-ownership authorization, shared by BranchScope
 * (query-level) and every branch-scoped model's policy (record-level) so the two
 * enforcement layers can never diverge on the rule. For a branch-owned model `X`:
 * `super_admin` and holders of the model-specific `ViewAllBranches:{X}` permission see
 * every branch; everyone else only their own `branch_id`. `{X}` is Shield's PascalCase
 * model-basename subject (e.g. `Application`), matching `config/filament-shield.php`'s
 * `permissions.case = 'pascal'` / `resources.subject = 'model'` convention.
 */
final class BranchAccess
{
    /**
     * @param  class-string  $modelClass
     */
    public static function canSeeAllBranches(AuthUser $user, string $modelClass): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('ViewAllBranches:'.class_basename($modelClass));
    }

    /**
     * True when $user may access a specific record's branch: cross-branch access per
     * canSeeAllBranches(), or the record's branch_id matches the user's own. A user with a
     * null branch_id and no cross-branch permission never matches any record's branch —
     * including a record whose own branch_id happens to be null.
     *
     * @param  class-string  $modelClass
     */
    public static function canAccessBranch(AuthUser $user, string $modelClass, ?int $recordBranchId): bool
    {
        if (self::canSeeAllBranches($user, $modelClass)) {
            return true;
        }

        return $user->branch_id !== null && $user->branch_id === $recordBranchId;
    }
}
