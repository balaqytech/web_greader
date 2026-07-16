<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use Carbon\CarbonImmutable;

/**
 * A checkout session the provider opened for us.
 *
 * `payload` is already sanitised by the adapter — it never carries API keys or raw provider
 * internals, because it is persisted onto the payment row and can surface in support and
 * audit views.
 */
final readonly class CheckoutSessionDTO
{
    /**
     * @param  array<string, mixed>  $payload  Sanitised provider response.
     */
    public function __construct(
        public string $sessionId,
        public string $checkoutUrl,
        public ?CarbonImmutable $expiresAt = null,
        public array $payload = [],
    ) {}
}
