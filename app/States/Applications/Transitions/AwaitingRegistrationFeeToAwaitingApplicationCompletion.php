<?php

declare(strict_types=1);

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Enums\PaymentPurpose;
use App\Exceptions\UnpaidRegistrationFeeException;
use App\Models\Application;
use App\Models\Payment;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * The registration-fee gate. The only way past it.
 *
 * There is deliberately no way to express "advance past the fee gate, trust me" — which is
 * exactly what the removed SubmitApplicationFilamentAction did, gated only by
 * `Update:Application`, a permission every branch staffer holds. A caller that supplies no
 * payment is refused with `UnpaidRegistrationFeeException::missing()` before any state is
 * written.
 *
 * `$payment` is nullable only because Spatie instantiates the transition with the model
 * alone when answering `canTransitionTo()` (see State::resolveTransitionClass) — a required
 * parameter would turn every visibility check in the Filament resources into a fatal
 * ArgumentCountError. The guard below, not the signature, is the enforcement.
 *
 * The payment is verified rather than taken on trust: it must be paid, must belong to *this*
 * application, and must actually be for the registration fee. Nothing here reads the fee
 * settings — what was owed was snapshotted onto the attempt when it was created, so changing
 * the fee later cannot retroactively invalidate a completed payment.
 *
 * Normally invoked from inside `PendingToPaid`'s transaction, which already holds the
 * application lock; `LockApplication::inState()` re-entering that lock in the same
 * transaction is a no-op on the same connection, and taking it here as well keeps this
 * transition correct when called on its own (e.g. bank verification, cash confirmation).
 */
class AwaitingRegistrationFeeToAwaitingApplicationCompletion extends Transition
{
    public function __construct(
        public Application $application,
        public ?Payment $payment = null,
        public ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        return DB::transaction(function () {
            $application = LockApplication::inState($this->application, AwaitingRegistrationFee::class);

            $this->guardPayment($application);

            $application->status = AwaitingApplicationCompletion::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                AwaitingRegistrationFee::$name,
                AwaitingApplicationCompletion::$name,
                $this->notes ?? __('alerts.payment.fee_settled', ['reference' => $this->payment?->reference]),
            );

            return $application;
        }, attempts: 3);
    }

    /**
     * Re-read under the lock rather than trusting the in-memory instance: the caller's copy
     * may predate another request settling or failing this attempt.
     *
     * @throws UnpaidRegistrationFeeException
     */
    private function guardPayment(Application $application): void
    {
        if ($this->payment === null) {
            throw UnpaidRegistrationFeeException::missing();
        }

        $payment = Payment::withoutGlobalScopes()
            ->whereKey($this->payment->getKey())
            ->lockForUpdate()
            ->first();

        if ($payment === null || ! $payment->isPaid()) {
            throw UnpaidRegistrationFeeException::notPaid($payment ?? $this->payment);
        }

        if ($payment->application_id !== $application->getKey()) {
            throw UnpaidRegistrationFeeException::wrongApplication($payment);
        }

        if ($payment->purpose !== PaymentPurpose::REGISTRATION_FEE) {
            throw UnpaidRegistrationFeeException::wrongPurpose($payment);
        }
    }
}
