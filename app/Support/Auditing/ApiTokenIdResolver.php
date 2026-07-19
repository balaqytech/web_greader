<?php

declare(strict_types=1);

namespace App\Support\Auditing;

use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

/**
 * Attributes an audited change to the Sanctum personal-access token that authenticated the
 * request. The raw token id is stored — deliberately without a foreign key — so revoking (i.e.
 * deleting) a token can never cascade away or null out the historical attribution of what that
 * token did while it was live. Returns null for non-API (session/console) changes.
 */
final class ApiTokenIdResolver implements Resolver
{
    public static function resolve(Auditable $auditable): ?int
    {
        $token = Auth::guard('sanctum')->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->getKey() : null;
    }
}
