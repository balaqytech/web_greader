<?php

declare(strict_types=1);

namespace App\Support\Payments\Evidence;

use App\Enums\PaymentMethod;
use App\Models\User;

/**
 * Central finance's decision that an uploaded bank receipt genuinely matches the attempt.
 * Only producible by the finance verification action — see `VerifyBankTransfer:Payment` —
 * which is why this class carries the verifying user rather than accepting one implicitly.
 */
final readonly class BankTransferVerificationEvidence implements PaymentSettlementEvidence
{
    public function __construct(
        public User $verifiedBy,
    ) {}

    public function method(): PaymentMethod
    {
        return PaymentMethod::BANK_TRANSFER;
    }
}
