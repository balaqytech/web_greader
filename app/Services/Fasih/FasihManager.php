<?php

declare(strict_types=1);

namespace App\Services\Fasih;

use App\Services\Fasih\Drivers\HttpFasihClient;
use App\Services\Fasih\Drivers\NullFasihClient;
use Illuminate\Support\Manager;

/**
 * Resolves the active Fasih notification driver, mirroring the payment gateway manager:
 * config-driven selection, per-driver memoisation, and `extend()` for tests/future providers.
 *
 * The default is the null driver — notifications are off unless a deployment explicitly turns
 * on the HTTP driver. Drivers are only ever handed out as {@see FasihClient}, so nothing
 * downstream can reach past the contract to a transport-specific method.
 *
 * @method FasihClient driver(?string $driver = null)
 */
class FasihManager extends Manager
{
    /**
     * A present-but-empty config value falls back to the null driver rather than resolving a
     * driver named "" (an empty `FASIH_DRIVER=` in an env file yields an empty string).
     */
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('services.fasih.driver');

        return is_string($driver) && trim($driver) !== '' ? $driver : 'null';
    }

    public function createHttpDriver(): FasihClient
    {
        return new HttpFasihClient($this->config->get('services.fasih', []));
    }

    /**
     * The disabled driver: a real, registered no-op so tests and undeployed environments
     * exercise the genuine resolution path rather than a mock.
     */
    public function createNullDriver(): FasihClient
    {
        return new NullFasihClient;
    }
}
