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

        $this->configurePaymentRateLimiter();
    }

    /**
     * 5/minute, per token — not per IP, since a single service integration behind one IP must
     * not be able to starve another's abusive traffic into looking like a shared limit, and a
     * token id cannot be spoofed the way an IP or a body field can.
     */
    protected function configurePaymentRateLimiter(): void
    {
        RateLimiter::for('payments', function (Request $request) {
            $key = $request->user()?->currentAccessToken()?->getKey() ?? $request->ip();

            return Limit::perMinute(5)->by('payments:'.$key);
        });
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
