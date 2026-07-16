<?php

declare(strict_types=1);

namespace App\Support\Payments\Evidence;

use App\Enums\PaymentMethod;
use App\Models\User;

/**
 * A staff member's confirmation that cash was physically received, with a reference and notes
 * for the audit trail. Only producible by the authorized cash-confirmation action — see
 * `ConfirmCash:Payment` — which is why this class carries the confirming user rather than
 * accepting one implicitly.
 */
final readonly class CashSettlementEvidence implements PaymentSettlementEvidence
{
    public function __construct(
        public User $confirmedBy,
        public string $reference,
        public string $notes,
    ) {}

    public function method(): PaymentMethod
    {
        return PaymentMethod::CASH;
    }
}
