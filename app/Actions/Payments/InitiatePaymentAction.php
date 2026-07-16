<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentInitiationException;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\Services\Payments\PaymentGateway;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Payments\Failed;
use App\States\Payments\Pending;
use App\Support\Payments\LockPayment;
use App\Support\Settings\PaymentSettings;
use Illuminate\Support\Facades\DB;

/**
 * The one way a registration-fee attempt is created — shared by the Filament staff action and
 * the chatbot API, so both are subject to the same guards rather than each growing its own.
 *
 * Authorization is the caller's job and happens before this.
 *
 * ## Why the provider call is outside the transaction
 *
 * The row is created under the application lock, then the transaction commits, and only then
 * is the provider called. Holding a row lock across an HTTP request would pin the
 * application row for the length of the provider's timeout, blocking every other operation
 * on it — an outage at Thawani would become an outage here.
 *
 * That split is safe because the attempt's own public reference is sent as the provider's
 * client reference: an attempt that exists locally but whose provider call never came back
 * can always be found again by asking the provider for that reference. Nothing is lost by
 * committing first.
 *
 * A provider failure is then classified rather than blanket-handled:
 *   - final (an explicit rejection) → the attempt is failed, freeing the application for a
 *     fresh attempt immediately;
 *   - retryable (a timeout or outage) → the attempt stays pending, because the provider may
 *     have created the session anyway, and reconciliation resolves it by client reference.
 *     Failing it here could abandon a session the guardian is about to pay.
 */
class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentSettings $settings,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @throws PaymentInitiationException
     * @throws PaymentGatewayException
     */
    public function execute(InitiatePaymentDTO $dto): Payment
    {
        $fee = $this->settings->registrationFee();

        if ($fee === null) {
            throw PaymentInitiationException::feeNotConfigured();
        }

        if ($dto->method === PaymentMethod::BANK_TRANSFER && ! $this->settings->isBankTransferConfigured()) {
            throw PaymentInitiationException::methodUnavailable($dto->method);
        }

        if ($dto->idempotencyKey !== null) {
            $replay = $this->replayOf($dto);

            if ($replay !== null) {
                return $replay;
            }
        }

        $payment = DB::transaction(function () use ($dto, $fee): Payment {
            $application = LockPayment::application($dto->application);

            if (! $application->status instanceof AwaitingRegistrationFee) {
                throw PaymentInitiationException::notAwaitingFee($application);
            }

            $active = $this->activeAttempt($application);

            if ($active !== null) {
                throw PaymentInitiationException::attemptAlreadyActive($active);
            }

            return Payment::create([
                'application_id' => $application->getKey(),
                // Denormalised from the *locked* application, never from the request.
                'branch_id' => $application->branch_id,
                'purpose' => PaymentPurpose::REGISTRATION_FEE,
                'method' => $dto->method,
                'status' => Pending::$name,
                // Snapshotted now. Changing the fee later must not alter what this attempt
                // was for.
                'amount' => $fee->value,
                'currency' => 'OMR',
                'idempotency_key' => $dto->idempotencyKey,
                'request_hash' => $dto->requestHash,
                'created_by' => $dto->actor?->getKey(),
            ]);
        }, attempts: 3);

        // Committed. Only now is the network touched.
        if ($dto->method === PaymentMethod::THAWANI) {
            $this->openCheckout($payment, $dto);
        }

        return $payment->refresh();
    }

    /**
     * Namespaces a caller-supplied idempotency key by the acting principal.
     *
     * Two callers picking the same obvious value ("1", "retry") must not collide, and no
     * caller may retrieve another's payment by guessing their key — the key is a lookup
     * handle, so an un-namespaced one would be an enumeration hole straight into other
     * applications' payments.
     */
    public static function namespacedKey(string $rawKey, ?string $principal): string
    {
        return sprintf('%s:%s', $principal ?? 'anonymous', $rawKey);
    }

    /**
     * An exact replay returns the original result; a key reused for a *different* request is
     * refused, because handing back the original would give the caller someone else's
     * payment.
     *
     * @throws PaymentInitiationException
     */
    private function replayOf(InitiatePaymentDTO $dto): ?Payment
    {
        $existing = Payment::withoutGlobalScope(BranchScope::class)
            ->where('idempotency_key', $dto->idempotencyKey)
            ->first();

        if ($existing === null) {
            return null;
        }

        if ($existing->request_hash !== $dto->requestHash) {
            throw PaymentInitiationException::idempotencyKeyReused();
        }

        return $existing;
    }

    private function activeAttempt(Application $application): ?Payment
    {
        return Payment::withoutGlobalScope(BranchScope::class)
            ->where('application_id', $application->getKey())
            ->forRegistrationFee()
            ->active()
            ->lockForUpdate()
            ->first();
    }

    /**
     * @throws PaymentGatewayException
     */
    private function openCheckout(Payment $payment, InitiatePaymentDTO $dto): void
    {
        try {
            $session = $this->gateway->createCheckout(new CheckoutRequestDTO(
                // The attempt's own public reference, so the session can always be found
                // again even if this response never arrives.
                clientReference: $payment->reference,
                amount: $payment->money(),
                productName: (string) config('payments.checkout.product_name'),
                successUrl: route('payments.return', ['payment' => $payment->reference, 'outcome' => 'success']),
                cancelUrl: route('payments.return', ['payment' => $payment->reference, 'outcome' => 'cancel']),
                metadata: ['application_reference' => (string) $dto->application->ref_no],
            ));
        } catch (PaymentGatewayException $e) {
            $this->handleCheckoutFailure($payment, $e);

            throw $e;
        }

        $payment->forceFill([
            'provider_session_id' => $session->sessionId,
            'provider_checkout_url' => $session->checkoutUrl,
            'provider_expires_at' => $session->expiresAt,
            'provider_payload' => $session->payload,
        ])->save();
    }

    /**
     * A final rejection frees the application for a fresh attempt straight away. A retryable
     * failure deliberately leaves the attempt pending: the provider may have created the
     * session regardless of our never hearing back, and reconciliation will find it by client
     * reference. Failing it here could abandon a session the guardian is about to pay.
     */
    private function handleCheckoutFailure(Payment $payment, PaymentGatewayException $e): void
    {
        if ($e->retryable) {
            return;
        }

        $payment->status->transitionTo(Failed::class, $e->getMessage());
    }
}
