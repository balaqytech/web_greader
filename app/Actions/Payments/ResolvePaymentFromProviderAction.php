<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Enums\PaymentMethod;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\StalePaymentStateException;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\Support\Payments\Evidence\ThawaniSettlementEvidence;
use Illuminate\Support\Facades\Log;

/**
 * Asks the provider what actually happened to a pending Thawani attempt, and applies it.
 *
 * The single place a Thawani payment is settled. Both callers — the browser return and the
 * reconciliation command — go through here, so "only a server-side retrieval settles a
 * payment" is enforced in one place rather than trusted to each entry point.
 *
 * A browser redirect is client-controlled: a guardian who edits `?outcome=success` in the URL
 * bar must gain nothing. The redirect is therefore treated purely as a hint that it is worth
 * asking; the answer only ever comes from the provider.
 *
 * A "paid" answer alone is not enough to settle. The provider's response is bound to *this*
 * attempt before anything is applied: the session id must match (or be safely recoverable by
 * client reference), the client reference must equal this attempt's own reference, the charged
 * amount must exactly equal what was snapshotted, and the currency must be OMR. Any mismatch is
 * a discrepancy, not a payment — evidence is preserved and logged, and the attempt is left
 * pending for manual reconciliation rather than settled or abandoned.
 *
 * Idempotent. Re-running it on an already-settled attempt is a no-op. The browser return and a
 * reconciliation pass can legitimately race on the same attempt: whichever loses the lock in
 * `PendingToPaid`/`PendingToFailed`/`PendingToExpired` simply re-reads the winner's persisted
 * result rather than raising — a stale-state loser must never surface as a server error or
 * abort a batch of other, unrelated reconciliations.
 */
class ResolvePaymentFromProviderAction
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Returns the payment as it now stands. Callers must read its state rather than assume:
     * an unresolved attempt legitimately comes back still pending.
     *
     * @throws PaymentGatewayException
     */
    public function execute(Payment $payment): Payment
    {
        // Already settled — nothing to ask, and nothing to apply twice. This is a cheap,
        // unlocked pre-check to avoid a needless provider call; the authoritative check is the
        // lock re-verification inside each transition below.
        if (! $payment->status instanceof Pending) {
            return $payment;
        }

        $status = $this->askProvider($payment);

        if ($status === null) {
            return $payment;
        }

        try {
            return match ($status->outcome) {
                ProviderPaymentOutcome::PAID => $this->settle($payment, $status),
                ProviderPaymentOutcome::FAILED => $payment->status->transitionTo(
                    Failed::class,
                    __('alerts.payment.provider_declined'),
                    $status->payload,
                ),
                ProviderPaymentOutcome::CANCELLED => $payment->status->transitionTo(
                    Failed::class,
                    __('alerts.payment.checkout_cancelled'),
                    $status->payload,
                ),
                ProviderPaymentOutcome::EXPIRED => $payment->status->transitionTo(
                    Expired::class,
                    $status->payload,
                ),
                // Still open, or a status this adapter does not recognise. Neither is an
                // outcome, so the attempt stays pending and is asked about again later.
                // Guessing here would either invent money or abandon it.
                ProviderPaymentOutcome::UNPAID, ProviderPaymentOutcome::UNKNOWN => $payment,
            };
        } catch (StalePaymentStateException) {
            // Another request (the browser return, or an overlapping reconciliation run)
            // already resolved this attempt under the lock this one lost. Its result is the
            // truth; re-reading it is correct, throwing or aborting a batch over it is not.
            return $payment->fresh() ?? $payment;
        }
    }

    /**
     * Falls back to the client reference when no session id was ever stored — which is
     * exactly the case initiation leaves behind when the provider call timed out after the
     * session had already been created. Without this, such an attempt could never be
     * resolved.
     *
     * @throws PaymentGatewayException
     */
    private function askProvider(Payment $payment): ?ProviderSessionStatusDTO
    {
        if ($payment->provider_session_id !== null) {
            return $this->gateway->retrieveSession($payment->provider_session_id);
        }

        return $this->gateway->retrieveByClientReference($payment->reference);
    }

    private function settle(Payment $payment, ProviderSessionStatusDTO $status): Payment
    {
        $discrepancy = $this->discrepancy($payment, $status);

        if ($discrepancy !== null) {
            $this->recordDiscrepancy($payment, $status, $discrepancy);

            return $payment->fresh() ?? $payment;
        }

        $evidence = new ThawaniSettlementEvidence(
            sessionId: $status->sessionId,
            clientReference: $status->clientReference,
            amount: $status->amount,
            currency: $status->currency,
            payload: $status->payload,
        );

        $settled = $payment->status->transitionTo(Paid::class, $evidence);

        if (! $settled->isPaid()) {
            Log::warning('A provider-confirmed payment did not settle; it was refused as a duplicate charge and needs manual reconciliation.', [
                'payment_reference' => $payment->reference,
            ]);
        }

        return $settled;
    }

    /**
     * Binds a "paid" provider answer to this specific attempt. Every check here guards against
     * a distinct way a "paid" response could otherwise be misapplied: a session belonging to a
     * different attempt, a client reference that was never ours, an amount the provider
     * charged that does not match what was owed, or a currency other than OMR.
     *
     * Returns a human-readable discrepancy reason, or null when the response is safely bound.
     */
    private function discrepancy(Payment $payment, ProviderSessionStatusDTO $status): ?string
    {
        if ($payment->method !== PaymentMethod::THAWANI) {
            return 'payment method is not thawani';
        }

        if ($payment->provider_session_id !== null && $status->sessionId !== $payment->provider_session_id) {
            return 'provider session id does not match the stored session';
        }

        if ($status->clientReference === null || $status->clientReference !== $payment->reference) {
            return 'provider client reference does not match this attempt';
        }

        if ($status->amount === null || ! $status->amount->equals($payment->money())) {
            return 'provider charged amount does not match the snapshotted amount';
        }

        if ($status->currency === null || $status->currency !== 'OMR') {
            return 'provider currency is not OMR';
        }

        return null;
    }

    private function recordDiscrepancy(Payment $payment, ProviderSessionStatusDTO $status, string $reason): void
    {
        Log::error('A provider "paid" response could not be safely bound to this payment attempt; it was left pending for manual reconciliation rather than settled.', [
            'payment_reference' => $payment->reference,
            'reason' => $reason,
            'provider_session_id' => $status->sessionId,
            'provider_client_reference' => $status->clientReference,
            'provider_amount' => $status->amount?->value,
            'provider_currency' => $status->currency,
        ]);

        // Sanitised evidence is preserved even though the attempt is not settled, so whoever
        // reconciles this by hand can see exactly what the provider reported.
        $payment->forceFill(['provider_payload' => $status->payload])->save();
    }
}
