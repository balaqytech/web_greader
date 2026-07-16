<?php

declare(strict_types=1);

namespace App\States\Payments\Transitions;

use App\Models\Payment;
use App\States\Payments\Failed;
use App\States\Payments\Pending;
use App\Support\Payments\LockPayment;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * The provider declined the attempt, or it ended in a technical failure. No human decided
 * this — see AwaitingVerificationToRejected for that.
 *
 * Only ever driven by a *confirmed* provider decision. An error while asking the provider
 * what happened must never land here: not knowing is not the same as knowing it failed, and
 * failing an attempt we could not read could abandon a payment the guardian genuinely made.
 *
 * The application is untouched — it simply stays at the fee gate, and the guardian may start
 * a fresh attempt.
 */
class PendingToFailed extends Transition
{
    /**
     * @param  array<string, mixed>|null  $providerPayload  Sanitised provider evidence.
     */
    public function __construct(
        public Payment $payment,
        public string $reason,
        public ?array $providerPayload = null,
    ) {}

    public function handle(): Payment
    {
        return DB::transaction(function () {
            $locked = LockPayment::inState($this->payment, Pending::class);

            $payment = $locked->payment;
            $payment->status = Failed::class;
            $payment->failure_reason = $this->reason;

            if ($this->providerPayload !== null) {
                $payment->provider_payload = $this->providerPayload;
            }

            $payment->save();

            return $payment;
        }, attempts: 3);
    }
}
