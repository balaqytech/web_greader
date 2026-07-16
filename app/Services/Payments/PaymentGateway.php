<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\CheckoutSessionDTO;
use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Exceptions\PaymentGatewayException;

/**
 * The payment domain's whole view of a payment provider.
 *
 * Everything crossing this boundary is an application-owned DTO. No vendor package type,
 * provider status string, response array, or baisa integer may leak past it — which is what
 * makes the provider replaceable without touching the domain or its callers.
 *
 * Deliberately absent: refunds. `jkbroot/thawani` exposes a Refunds API, but refunds are out
 * of scope for this domain, and the surest way to keep it that way is to give the domain no
 * vocabulary for them.
 *
 * Every method throws only `PaymentGatewayException`, whose `retryable` flag tells the caller
 * whether the outcome is genuinely unknown (stay pending) or final (settle).
 */
interface PaymentGateway
{
    /**
     * Opens a checkout session and returns somewhere to send the guardian.
     *
     * @throws PaymentGatewayException
     */
    public function createCheckout(CheckoutRequestDTO $request): CheckoutSessionDTO;

    /**
     * Asks the provider what actually happened to a session. This is the *only* thing
     * allowed to settle a Thawani payment — a browser redirect is client-controlled and
     * proves nothing.
     *
     * @throws PaymentGatewayException
     */
    public function retrieveSession(string $sessionId): ProviderSessionStatusDTO;

    /**
     * Finds a session by the reference we gave it. The recovery path for when we never
     * learned the session id — e.g. the create request timed out after the provider had
     * already created the session. Null when the provider knows of no such session.
     *
     * @throws PaymentGatewayException
     */
    public function retrieveByClientReference(string $clientReference): ?ProviderSessionStatusDTO;

    /**
     * Best-effort cancellation of an open session. Returns false when the provider declines
     * to cancel (typically because the session is already settled) — that is an answer, not
     * an error, so callers must not treat it as failure.
     *
     * @throws PaymentGatewayException
     */
    public function cancelSession(string $sessionId): bool;
}
