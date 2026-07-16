<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Payments\Evidence\PaymentSettlementEvidence;
use Exception;

/**
 * A `Pending -> Paid` (or `AwaitingVerification -> Paid`) transition was invoked with evidence
 * that does not belong to the payment being settled. Always thrown before any state or
 * activity change — never after.
 */
class InvalidSettlementEvidenceException extends Exception
{
    public static function methodMismatch(Payment $payment, PaymentSettlementEvidence $evidence): self
    {
        return new self(sprintf(
            'Payment %s uses method %s but was settled with %s evidence.',
            $payment->reference ?? '(unsaved)',
            $payment->method->value,
            class_basename($evidence),
        ));
    }

    /**
     * A method-specific edge (bank-transfer verification upload, cash confirmation, ...) was
     * driven by a payment that does not use that method at all.
     */
    public static function methodNotEligible(Payment $payment, PaymentMethod $required): self
    {
        return new self(sprintf(
            'Payment %s uses method %s, which is not eligible for this operation (requires %s).',
            $payment->reference ?? '(unsaved)',
            $payment->method->value,
            $required->value,
        ));
    }
}
