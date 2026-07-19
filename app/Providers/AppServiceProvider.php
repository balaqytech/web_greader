<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerPaymentGateway();
    }

    /**
     * The manager is a singleton so its per-driver memoisation — and anything a test
     * registers through `extend()` — is shared across a request.
     *
     * `PaymentGateway` resolves to the active driver, so callers type-hint the contract and
     * never the manager, the driver, or anything from the vendor package.
     */
    protected function registerPaymentGateway(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);

        $this->app->bind(
            PaymentGateway::class,
            fn ($app): PaymentGateway => $app->make(PaymentGatewayManager::class)->driver(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        LogViewer::auth(fn ($request) => Auth::check());

        FilamentShield::prohibitDestructiveCommands(app()->isProduction());

        $this->configureApiRateLimiters();
    }

    /**
     * Service-API limits are keyed on the Sanctum token id — not the IP — so each integration
     * gets an isolated budget: one token id cannot be spoofed the way an IP or a body field
     * can, and two integrations behind the same IP cannot starve each other. Reads, writes,
     * and payment operations get separate buckets (distinct key prefixes) so a burst of reads
     * never consumes a token's much smaller write allowance. Public catalogs have no token, so
     * they fall back to per-IP limiting.
     */
    protected function configureApiRateLimiters(): void
    {
        // These limits sit behind `auth:sanctum`, so the principal is resolved on the sanctum
        // guard — not the default `web` guard, which would report null here and silently
        // collapse every token onto a shared per-IP bucket.
        $byToken = fn (Request $request): string => (string) ($request->user('sanctum')?->currentAccessToken()?->getKey() ?? $request->ip());

        RateLimiter::for('api-read', fn (Request $request) => Limit::perMinute(60)->by('api-read:'.$byToken($request)));

        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(10)->by('api-write:'.$byToken($request)));

        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(5)->by('payments:'.$byToken($request)));

        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(60)->by('api-public:'.$request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(8)
                    ->letters()
                    ->numbers()
                    ->uncompromised()
                : null,
        );
    }
}
