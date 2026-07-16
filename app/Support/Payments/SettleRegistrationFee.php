<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\PaymentPurpose;
use App\Models\Payment;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use Illuminate\Support\Facades\Log;

/**
 * The single owner of "this attempt is now paid — what happens to the application?".
 *
 * Shared by every route to Paid (provider retrieval, bank verification, cash confirmation)
 * so the double-charge rule and the fee-gate advance cannot be implemented three subtly
 * different ways.
 *
 * Callers must already hold the application and payment locks (pass a `LockedPayment`) and
 * must already be inside a transaction. Marking the payment and advancing the application
 * happen together, so a fee that is paid while its application still waits for it is
 * impossible.
 */
class SettleRegistrationFee
{
    /**
     * Settles a locked attempt.
     *
     * Returns the payment as it ended up — which is normally Paid, but is **Failed** when a
     * second successful charge is detected. Callers must read the returned state rather than
     * assume success.
     *
     * @param  array<string, mixed>|null  $providerPayload  Sanitised provider evidence.
     */
    public function settle(LockedPayment $locked, ?array $providerPayload = null, ?string $notes = null): Payment
    {
        $payment = $locked->payment;
        $application = $locked->application;

        if ($providerPayload !== null) {
            $payment->provider_payload = $providerPayload;
        }

        $alreadyPaid = $this->existingPaidFee($payment);

        if ($alreadyPaid !== null) {
            return $this->refuseDoubleCharge($payment, $alreadyPaid);
        }

        $payment->status = Paid::class;
        $payment->save();

        // An application already past the gate is left alone. That is not an error: legacy
        // applications were grandfathered past the fee without a payment record, and a fee
        // settled against one of them must not drag it backwards or advance it twice.
        if ($application->status instanceof AwaitingRegistrationFee) {
            $application->status->transitionTo(AwaitingApplicationCompletion::class, $payment, $notes);
        }

        return $payment;
    }

    /**
     * Another paid registration-fee attempt on the same application. Locked, because the
     * whole point is to serialise against a concurrent settlement.
     */
    private function existingPaidFee(Payment $payment): ?Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('application_id', $payment->application_id)
            ->where('purpose', PaymentPurpose::REGISTRATION_FEE)
            ->whereKeyNot($payment->getKey())
            ->paid()
            ->lockForUpdate()
            ->first();
    }

    /**
     * The application was already paid for, and the provider has now reported a second
     * successful charge. This should be impossible — one active attempt per application is
     * enforced by the application lock — so reaching here means real money may have been
     * taken twice.
     *
     * We refuse to advance the application twice, and we do NOT silently mark this attempt
     * paid, which would leave two paid rows and no signal that anything was wrong. The
     * losing attempt is failed with an explicit reason, its provider evidence is preserved
     * for whoever reconciles it, and it is reported loudly. This deliberately does not throw:
     * the money situation needs a human, and an exception here would roll back the very
     * evidence they need.
     */
    private function refuseDoubleCharge(Payment $payment, Payment $winner): Payment
    {
        $payment->status = Failed::class;
        $payment->failure_reason = __('alerts.payment.double_charge_needs_reconciliation', [
            'reference' => $winner->reference,
        ]);
        $payment->save();

        Log::error('A second successful registration-fee charge was reported for an application that is already paid. The application was not advanced twice, and the losing attempt has been failed pending manual reconciliation — real money may have been captured twice.', [
            'application_id' => $payment->application_id,
            'losing_payment_reference' => $payment->reference,
            'winning_payment_reference' => $winner->reference,
            'losing_payment_provider_session_id' => $payment->provider_session_id,
            'losing_payment_amount' => $payment->amount,
        ]);

        return $payment;
    }
}
