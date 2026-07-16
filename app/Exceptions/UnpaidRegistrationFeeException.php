<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Payment;
use Exception;

class UnpaidRegistrationFeeException extends Exception
{
    /**
     * No payment was supplied at all — i.e. a caller tried to advance an application past
     * the fee gate on nothing but its own say-so.
     */
    public static function missing(): self
    {
        return new self(
            'An application cannot advance past the registration-fee gate without a paid registration-fee payment. '
            .'Pass the triggering payment to the transition; there is no way to advance the gate without one.'
        );
    }

    public static function notPaid(Payment $payment): self
    {
        return new self(sprintf(
            'Payment %s is %s, not paid; it cannot advance an application past the registration-fee gate.',
            $payment->reference,
            $payment->status::$name,
        ));
    }

    public static function wrongApplication(Payment $payment): self
    {
        return new self(sprintf(
            'Payment %s belongs to application %d and cannot advance a different application.',
            $payment->reference,
            $payment->application_id,
        ));
    }

    public static function wrongPurpose(Payment $payment): self
    {
        return new self(sprintf(
            'Payment %s is for %s, not the registration fee.',
            $payment->reference,
            $payment->purpose->value,
        ));
    }
}
