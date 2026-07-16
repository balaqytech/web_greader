<?php

declare(strict_types=1);

namespace App\States\Payments\Transitions;

use App\Models\Payment;
use App\States\Payments\Pending;
use App\Support\Payments\LockPayment;
use App\Support\Payments\SettleRegistrationFee;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Settles a pending attempt and advances its application past the fee gate in one
 * transaction, so a payment that is paid while its application still waits for the fee is
 * impossible.
 *
 * Callers must have established that the money genuinely moved *before* invoking this: a
 * server-side session retrieval, or a cash confirmation. This transition does not talk to
 * the provider — it records a decision already made.
 *
 * Returns the payment as it ended up. Normally Paid, but **Failed** if a second successful
 * charge was detected; see `SettleRegistrationFee`. Callers must read the returned state
 * rather than assume success.
 */
class PendingToPaid extends Transition
{
    /**
     * @param  array<string, mixed>|null  $providerPayload  Sanitised provider evidence.
     */
    public function __construct(
        public Payment $payment,
        public ?array $providerPayload = null,
        public ?string $notes = null,
    ) {}

    public function handle(): Payment
    {
        return DB::transaction(function () {
            // Application first, payment second — the project's lock order.
            $locked = LockPayment::inState($this->payment, Pending::class);

            return app(SettleRegistrationFee::class)->settle(
                $locked,
                $this->providerPayload,
                $this->notes,
            );
        }, attempts: 3);
    }
}
