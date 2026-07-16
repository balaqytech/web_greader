<?php

declare(strict_types=1);

namespace App\Services\Payments\Drivers;

use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\CheckoutSessionDTO;
use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payments\PaymentGateway;
use App\Support\Money\OmrAmount;
use Carbon\CarbonImmutable;

/**
 * Deterministic in-memory gateway for tests and local development.
 *
 * Implements the same contract as the real adapter, so a test drives the genuine domain
 * code path rather than a mock of it — including the rule that only a server-side retrieval
 * can settle a payment.
 *
 * Nothing here reaches a network, and no test needs credentials.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, ProviderSessionStatusDTO> */
    private array $sessions = [];

    /** @var array<string, string> Client reference => session id. */
    private array $references = [];

    private ?PaymentGatewayException $failure = null;

    private int $sessionCounter = 0;

    /**
     * Makes the next call throw. Use it to drive the caller's handling of an unreachable
     * provider (retryable — attempt stays pending) versus a rejection (final).
     */
    public function willFailWith(PaymentGatewayException $exception): void
    {
        $this->failure = $exception;
    }

    public function stopFailing(): void
    {
        $this->failure = null;
    }

    /**
     * Simulates the provider deciding an outcome out-of-band — the guardian paying on
     * Thawani's hosted page, a session lapsing, a card being declined. The domain only ever
     * learns about it through `retrieveSession()`, exactly as in production.
     */
    public function settle(string $sessionId, ProviderPaymentOutcome $outcome, ?OmrAmount $amount = null): void
    {
        $existing = $this->sessions[$sessionId] ?? null;

        $this->sessions[$sessionId] = new ProviderSessionStatusDTO(
            sessionId: $sessionId,
            outcome: $outcome,
            amount: $amount ?? $existing?->amount,
            clientReference: $existing?->clientReference,
            payload: ['session_id' => $sessionId, 'payment_status' => $outcome->value],
        );
    }

    public function createCheckout(CheckoutRequestDTO $request): CheckoutSessionDTO
    {
        $this->guard();

        $sessionId = sprintf('fake_sess_%04d', ++$this->sessionCounter);

        $this->sessions[$sessionId] = new ProviderSessionStatusDTO(
            sessionId: $sessionId,
            outcome: ProviderPaymentOutcome::UNPAID,
            amount: $request->amount,
            clientReference: $request->clientReference,
            payload: ['session_id' => $sessionId, 'payment_status' => 'unpaid'],
        );

        $this->references[$request->clientReference] = $sessionId;

        return new CheckoutSessionDTO(
            sessionId: $sessionId,
            checkoutUrl: "https://fake-checkout.test/pay/{$sessionId}",
            expiresAt: CarbonImmutable::now()->addDay(),
            payload: ['session_id' => $sessionId, 'payment_status' => 'unpaid'],
        );
    }

    public function retrieveSession(string $sessionId): ProviderSessionStatusDTO
    {
        $this->guard();

        return $this->sessions[$sessionId] ?? throw PaymentGatewayException::unexpectedResponse(
            "the provider knows of no session [{$sessionId}]."
        );
    }

    public function retrieveByClientReference(string $clientReference): ?ProviderSessionStatusDTO
    {
        $this->guard();

        $sessionId = $this->references[$clientReference] ?? null;

        return $sessionId === null ? null : $this->sessions[$sessionId];
    }

    public function cancelSession(string $sessionId): bool
    {
        $this->guard();

        $session = $this->sessions[$sessionId] ?? null;

        // Mirrors the real provider: an already-settled session cannot be cancelled, and
        // saying so is an answer rather than an error.
        if ($session === null || $session->outcome->isTerminal()) {
            return false;
        }

        $this->settle($sessionId, ProviderPaymentOutcome::CANCELLED);

        return true;
    }

    /**
     * @throws PaymentGatewayException
     */
    private function guard(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
