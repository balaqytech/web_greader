<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Api\FasihServiceAccount;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the service API on a *real* Sanctum personal-access token owned by the dedicated
 * `service_fasih` user. `auth:sanctum` alone is not enough: it also accepts a session
 * (SPA/`TransientToken`) credential, and any authenticated human could mint a token that
 * happens to carry the same abilities. This middleware rejects both — the credential must be
 * a persisted `PersonalAccessToken`, and its owner must hold the service role — so the only
 * key that opens these routes is one issued by `fasih:issue-token`.
 */
class EnsureFasihServiceAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = is_object($user) && method_exists($user, 'currentAccessToken')
            ? $user->currentAccessToken()
            : null;

        if (! $token instanceof PersonalAccessToken || ! FasihServiceAccount::isServiceAccount($user)) {
            abort(Response::HTTP_FORBIDDEN, __('alerts.api.service_account_required'));
        }

        return $next($request);
    }
}
