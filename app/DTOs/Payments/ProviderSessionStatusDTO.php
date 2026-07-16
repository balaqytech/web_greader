<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Enums\ProviderPaymentOutcome;
use App\Support\Money\OmrAmount;

/**
 * The provider's authoritative answer about a session, normalised.
 *
 * This is the only thing that may settle a Thawani payment. A browser redirect claiming
 * success is client-controlled and proves nothing; the domain acts on this DTO, which is
 * always the product of a server-to-server retrieval.
 *
 * `amount` is what the provider says was charged, so a caller can compare it against the
 * fee snapshotted on the attempt and refuse to settle a mismatch. Null when the provider
 * did not report one.
 */
final readonly class ProviderSessionStatusDTO
{
    /**
     * @param  array<string, mixed>  $payload  Sanitised provider response.
     */
    public function __construct(
        public string $sessionId,
        public ProviderPaymentOutcome $outcome,
        public ?OmrAmount $amount = null,
        public ?string $clientReference = null,
        public array $payload = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->outcome === ProviderPaymentOutcome::PAID;
    }
}
