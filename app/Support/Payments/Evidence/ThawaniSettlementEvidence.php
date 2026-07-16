<?php

declare(strict_types=1);

namespace App\Support\Payments\Evidence;

use App\Enums\PaymentMethod;
use App\Support\Money\OmrAmount;

/**
 * A server-verified Thawani session confirming an exact charge against a specific attempt.
 *
 * Building one of these is only legitimate once every identity, amount and currency check in
 * `ResolvePaymentFromProviderAction` has already passed — this class does not repeat those
 * checks itself; it is the *record* that they passed, not the thing that decides they did.
 */
final readonly class ThawaniSettlementEvidence implements PaymentSettlementEvidence
{
    /**
     * @param  array<string, mixed>  $payload  Sanitised provider response.
     */
    public function __construct(
        public string $sessionId,
        public string $clientReference,
        public OmrAmount $amount,
        public string $currency,
        public array $payload = [],
    ) {}

    public function method(): PaymentMethod
    {
        return PaymentMethod::THAWANI;
    }
}
