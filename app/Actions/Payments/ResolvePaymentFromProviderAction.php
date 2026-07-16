<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
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
 * Idempotent. Re-running it on an already-settled attempt is a no-op, so a refreshed return
 * page or an overlapping reconciliation run cannot double-apply an outcome.
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
        // Already settled — nothing to ask, and nothing to apply twice.
        if (! $payment->status instanceof Pending) {
            return $payment;
        }

        $status = $this->askProvider($payment);

        if ($status === null) {
            return $payment;
        }

        return match ($status->outcome) {
            ProviderPaymentOutcome::PAID => $this->settle($payment, $status->payload),
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
            // Still open, or a status this adapter does not recognise. Neither is an outcome,
            // so the attempt stays pending and is asked about again later. Guessing here
            // would either invent money or abandon it.
            ProviderPaymentOutcome::UNPAID, ProviderPaymentOutcome::UNKNOWN => $payment,
        };
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function settle(Payment $payment, array $payload): Payment
    {
        $settled = $payment->status->transitionTo(Paid::class, $payload);

        if (! $settled->isPaid()) {
            Log::warning('A provider-confirmed payment did not settle; it was refused as a duplicate charge and needs manual reconciliation.', [
                'payment_reference' => $payment->reference,
            ]);
        }

        return $settled;
    }
}
