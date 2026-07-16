<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PaymentMethod;
use App\Models\Application;
use App\Models\Payment;
use Exception;

/**
 * Why an attempt could not be created. Every case is a refusal to take money in a situation
 * the domain cannot make sense of — never a provider problem, which is
 * `PaymentGatewayException`.
 */
class PaymentInitiationException extends Exception
{
    /**
     * There is no configured fee, so nobody can say what is owed. Deliberately fatal rather
     * than defaulted: a guessed or zero fee would let applications through the gate for free.
     */
    public static function feeNotConfigured(): self
    {
        return new self(__('alerts.payment.fee_not_configured'));
    }

    public static function methodUnavailable(PaymentMethod $method): self
    {
        return new self(match ($method) {
            PaymentMethod::BANK_TRANSFER => __('alerts.payment.bank_transfer_not_configured'),
            default => __('alerts.payment.method_unavailable', ['method' => $method->value]),
        });
    }

    public static function notAwaitingFee(Application $application): self
    {
        return new self(__('alerts.payment.not_awaiting_fee'));
    }

    /**
     * One active attempt per application at a time. Not a race guard bolted on — the caller
     * is told the existing attempt so it can point the guardian back at it rather than
     * silently opening a second checkout for the same fee.
     */
    public static function attemptAlreadyActive(Payment $existing): self
    {
        return new self(__('alerts.payment.attempt_already_active'));
    }

    /**
     * The idempotency key was seen before with a *different* request. Returning the original
     * payment here would hand the caller someone else's result; creating a new one would
     * defeat the key. Refusing is the only honest answer.
     */
    public static function idempotencyKeyReused(): self
    {
        return new self(__('alerts.payment.idempotency_key_reused'));
    }
}
