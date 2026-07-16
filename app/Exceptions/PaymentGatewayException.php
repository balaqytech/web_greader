<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * The single exception type the payment gateway boundary throws. The adapter normalises
 * every provider- and package-specific failure into this, so callers never catch a bare
 * `\Exception` from the vendor package or a Guzzle/Illuminate HTTP exception.
 *
 * `retryable` is the important part, and it is a money-safety decision rather than a
 * convenience flag:
 *
 *   - **Retryable** — we never learned what the provider decided (connection refused,
 *     timeout, unparseable response, provider 5xx). The provider may well have created or
 *     even captured the session. The attempt must stay pending and be resolved by *asking*
 *     the provider later, never by assuming. Failing it here could abandon a real payment
 *     the guardian already made.
 *   - **Not retryable** — we are misconfigured, or the provider gave an explicit, final
 *     answer. Retrying reproduces the same result.
 */
class PaymentGatewayException extends Exception
{
    private function __construct(
        string $message,
        public readonly bool $retryable,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Credentials or endpoints are missing/unusable. Not retryable: no amount of retrying
     * supplies a secret key. Never carries the offending value — this message reaches logs.
     */
    public static function misconfigured(string $reason): self
    {
        return new self("Thawani is not correctly configured: {$reason}", retryable: false);
    }

    /**
     * The provider could not be reached, or did not answer in time. Retryable — and
     * critically, the request may still have taken effect on their side.
     */
    public static function unreachable(Throwable $previous): self
    {
        return new self(
            'The payment provider could not be reached; the attempt is unresolved and must be retried or reconciled, not failed.',
            retryable: true,
            previous: $previous,
        );
    }

    /**
     * The provider answered with an explicit error. Final — this is a decision, not an
     * outage.
     */
    public static function rejected(string $message, int $code = 0, ?Throwable $previous = null): self
    {
        return new self("The payment provider rejected the request: {$message}", retryable: false, code: $code, previous: $previous);
    }

    /**
     * The provider answered, but not in a shape this adapter understands. Retryable: we did
     * not learn what was decided, so we must not conclude anything.
     */
    public static function unexpectedResponse(string $reason, ?Throwable $previous = null): self
    {
        return new self("The payment provider returned an unexpected response: {$reason}", retryable: true, previous: $previous);
    }
}
