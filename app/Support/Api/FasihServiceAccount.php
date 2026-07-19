<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Models\User;

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

    /**
     * Operational roles grant panel access and/or branch-scoped authority. A user carrying any
     * of them is a human staff account and must not be repurposed as the headless service
     * principal.
     *
     * @var list<string>
     */
    public const OperationalRoles = [
        'super_admin',
        'branch_staff',
        'branch_manager',
        'central_finance',
        'panel_user',
    ];

    public static function isServiceAccount(?User $user): bool
    {
        return $user !== null && $user->hasRole(self::Role);
    }
}
