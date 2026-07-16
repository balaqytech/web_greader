<?php

declare(strict_types=1);

namespace App\States\Payments\Transitions;

use App\Models\Payment;
use App\Models\User;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Rejected;
use App\Support\Payments\LockPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\ModelStates\Transition;

/**
 * Central finance decided an uploaded bank receipt does not hold up — a human said no, not a
 * provider decline, which is why this is `Rejected` and never `Failed`.
 *
 * A blank reason is refused before any state changes: whoever disputes this needs to be told
 * why, and an empty reason is not an answer.
 *
 * The application is untouched — it stays at the fee gate so the guardian may try again.
 */
class AwaitingVerificationToRejected extends Transition
{
    /**
     * `$reason`/`$rejectedBy` are nullable only because Spatie instantiates the transition
     * with the model alone when answering `canTransitionTo()` — see `PendingToPaid` for why.
     * The guard in `handle()` is the real enforcement.
     */
    public function __construct(
        public Payment $payment,
        public ?string $reason = null,
        public ?User $rejectedBy = null,
    ) {}

    public function handle(): Payment
    {
        if ($this->reason === null || trim($this->reason) === '' || $this->rejectedBy === null) {
            throw new InvalidArgumentException('A bank-transfer rejection must carry a non-blank reason and a rejecting user.');
        }

        return DB::transaction(function () {
            $locked = LockPayment::inState($this->payment, AwaitingVerification::class);
            $payment = $locked->payment;

            $payment->status = Rejected::class;
            $payment->rejection_reason = $this->reason;
            $payment->verified_by = $this->rejectedBy->getKey();
            $payment->verified_at = now();
            $payment->save();

            return $payment;
        }, attempts: 3);
    }
}
