<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Payment;
use Exception;

class StalePaymentStateException extends Exception
{
    public static function make(Payment $payment, string $expectedState, mixed $actualState): self
    {
        return new self(sprintf(
            'Payment %s is no longer in %s (found %s); the attempt was resolved by another request.',
            $payment->reference ?? '(unsaved)',
            class_basename($expectedState),
            is_object($actualState) ? class_basename($actualState) : 'nothing',
        ));
    }
}
