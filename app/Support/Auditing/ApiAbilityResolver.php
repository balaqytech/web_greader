<?php

declare(strict_types=1);

namespace App\Support\Auditing;

use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

/**
 * Records which service ability authorized an audited change, read from the `abilities:` /
 * `ability:` middleware guarding the current route. This is the specific operation that was
 * exercised (e.g. `leads:create`), not the full set the token happens to hold, so the audit
 * trail says what the token was allowed to do here. Returns null unless the request was
 * authenticated by a real personal-access token.
 */
final class ApiAbilityResolver implements Resolver
{
    public static function resolve(Auditable $auditable): ?string
    {
        $token = Auth::guard('sanctum')->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        $route = request()?->route();

        if ($route === null) {
            return null;
        }

        $abilities = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (str_starts_with($middleware, 'abilities:') || str_starts_with($middleware, 'ability:')) {
                $params = substr($middleware, strpos($middleware, ':') + 1);

                foreach (explode(',', $params) as $ability) {
                    $ability = trim($ability);

                    if ($ability !== '') {
                        $abilities[] = $ability;
                    }
                }
            }
        }

        return $abilities === [] ? null : implode(',', array_unique($abilities));
    }
}
