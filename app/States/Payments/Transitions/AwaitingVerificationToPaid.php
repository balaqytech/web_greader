<?php

declare(strict_types=1);

namespace App\States\Payments\Transitions;

use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\Support\Payments\Evidence\BankTransferVerificationEvidence;
use App\Support\Payments\LockPayment;
use App\Support\Payments\SettleRegistrationFee;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\ModelStates\Transition;

/**
 * Central finance verified an uploaded bank receipt against the attempt. Settles it and
 * advances the application past the fee gate in one transaction, exactly like `PendingToPaid`
 * for the other methods.
 *
 * `SettleRegistrationFee` refuses this evidence for any payment that is not bank transfer, so
 * this edge cannot be used to paper over a Thawani or cash attempt.
 */
class AwaitingVerificationToPaid extends Transition
{
    /**
     * `$evidence` is nullable only because Spatie instantiates the transition with the model
     * alone when answering `canTransitionTo()` — see `PendingToPaid` for why. The guard in
     * `handle()` is the real enforcement.
     */
    public function __construct(
        public Payment $payment,
        public ?BankTransferVerificationEvidence $evidence = null,
        public ?string $notes = null,
    ) {}

    public function handle(): Payment
    {
        if ($this->evidence === null) {
            throw new InvalidArgumentException('A bank-transfer payment cannot be verified without verification evidence.');
        }

        return DB::transaction(function () {
            $locked = LockPayment::inState($this->payment, AwaitingVerification::class);

            return app(SettleRegistrationFee::class)->settle(
                $locked,
                $this->evidence,
                $this->notes,
            );
        }, attempts: 3);
    }
}
