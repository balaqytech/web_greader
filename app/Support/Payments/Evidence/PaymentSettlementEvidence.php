<?php

declare(strict_types=1);

namespace App\Support\Payments\Evidence;

use App\Enums\PaymentMethod;

/**
 * Proof that a specific method's payment genuinely settled, or was authoritatively verified.
 *
 * A `Pending -> Paid` (or `AwaitingVerification -> Paid`) transition may only be driven by one
 * of these — never a bare array or notes string — so a caller cannot mark a payment paid
 * without producing evidence appropriate to how that method is actually confirmed. Each
 * concrete class carries only what its method can genuinely prove: a browser click, a session
 * id, or a staff member's say-so are not interchangeable.
 */
interface PaymentSettlementEvidence
{
    /**
     * The method this evidence proves. `SettleRegistrationFee` refuses evidence whose method
     * does not match the payment being settled, so evidence built for one method can never be
     * replayed against a payment of another.
     */
    public function method(): PaymentMethod;
}
