<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Support\Money\OmrAmount;

/**
 * What this domain asks a gateway to open a checkout for. Provider-neutral: no Thawani
 * vocabulary, no baisa, no product arrays — the adapter translates.
 *
 * `clientReference` is the payment's public ULID. It is what lets us find a session again
 * during reconciliation when we never received a session id back (e.g. the response timed
 * out), which is the whole reason it is required rather than optional.
 */
final readonly class CheckoutRequestDTO
{
    /**
     * @param  array<string, string>  $metadata  Non-sensitive values echoed back by the provider.
     */
    public function __construct(
        public string $clientReference,
        public OmrAmount $amount,
        public string $productName,
        public string $successUrl,
        public string $cancelUrl,
        public array $metadata = [],
    ) {}
}
