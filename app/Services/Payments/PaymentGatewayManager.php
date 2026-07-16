<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\Drivers\ThawaniGateway;
use Illuminate\Support\Manager;

/**
 * Resolves the active payment gateway driver.
 *
 * Laravel's Manager gives this three things worth having: config-driven driver selection,
 * per-driver memoisation, and `extend()` — so a test (or a future provider) can register a
 * driver without this class knowing about it.
 *
 * Drivers are only ever handed out as `PaymentGateway`, so nothing downstream can reach for
 * a Thawani-specific method and quietly re-couple the domain to the provider.
 *
 * @method PaymentGateway driver(?string $driver = null)
 */
class PaymentGatewayManager extends Manager
{
    /**
     * A present-but-empty config value falls back rather than resolving to a driver named
     * "" — `config->get()`'s default does not cover an explicit null, and `PAYMENT_GATEWAY=`
     * in an env file yields an empty string, so both are normalised here.
     */
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('payments.gateway');

        return is_string($driver) && trim($driver) !== '' ? $driver : 'thawani';
    }

    public function createThawaniDriver(): PaymentGateway
    {
        return new ThawaniGateway;
    }

    /**
     * Deterministic, network-free driver for tests and local development. Registered as a
     * real driver rather than left to mocks so tests exercise the genuine domain code path.
     */
    public function createFakeDriver(): PaymentGateway
    {
        return new FakePaymentGateway;
    }
}
