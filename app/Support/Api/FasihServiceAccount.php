<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Identity of the dedicated Fasih integration principal. The service account is a real,
 * branchless `User` that holds only the `service_fasih` role (zero Shield permissions) and
 * authenticates the API exclusively through a personal-access token. It must never carry an
 * operational role or a branch assignment — those belong to humans who can reach the admin
 * panel, which this account is unconditionally barred from (see User::canAccessPanel()).
 */
final class FasihServiceAccount
{
    public const Role = 'service_fasih';

    public const TokenName = 'fasih-service';

    public static function isServiceAccount(?User $user): bool
    {
        if ($user === null || $user->branch_id !== null) {
            return false;
        }

        $roles = $user->getRoleNames();

        return $roles->count() === 1
            && $roles->contains(self::Role)
            && $user->getAllPermissions()->isEmpty();
    }

    public static function isServiceToken(PersonalAccessToken $token): bool
    {
        $abilities = is_array($token->abilities) ? $token->abilities : [];

        return $token->name === self::TokenName
            && array_diff($abilities, FasihServiceAbilities::all()) === [];
    }
}
