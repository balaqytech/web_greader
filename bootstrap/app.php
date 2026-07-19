<?php

use App\Http\Middleware\EnforceApiIdempotency;
use App\Http\Middleware\EnsureFasihServiceAccount;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('affiliate')
                ->group(base_path('routes/affiliate.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'fasih.service' => EnsureFasihServiceAccount::class,
            'api.idempotency' => EnforceApiIdempotency::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->routeIs('affiliate.*')
                ? route('affiliate.login')
                : route('login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request) => $request->routeIs('affiliate.*')
                ? route('affiliate.dashboard')
                : route('dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('affiliate/*')) {
                return redirect()->guest(route('affiliate.login'));
            }
        });
    })->create();
