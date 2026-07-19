<?php

declare(strict_types=1);

namespace App\Services\Fasih\Drivers;

use App\Services\Fasih\FasihClient;

/**
 * The disabled Fasih driver: every notification is a deliberate no-op. This is the default, so
 * an unconfigured environment (local, CI, a deployment that has not set FASIH_DRIVER=http)
 * silently sends nothing instead of erroring or posting to a wrong endpoint.
 */
final class NullFasihClient implements FasihClient
{
    public function leadCreated(array $payload): void
    {
        // Intentionally does nothing.
    }

    public function affiliateVerified(array $payload): void
    {
        // Intentionally does nothing.
    }
}
