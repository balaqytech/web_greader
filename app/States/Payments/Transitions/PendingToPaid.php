<?php

declare(strict_types=1);

namespace App\States\Payments\Transitions;

use App\Exceptions\InvalidSettlementEvidenceException;
use App\Models\Payment;
use App\States\Payments\Pending;
use App\Support\Payments\Evidence\CashSettlementEvidence;
use App\Support\Payments\Evidence\PaymentSettlementEvidence;
use App\Support\Payments\Evidence\ThawaniSettlementEvidence;
use App\Support\Payments\LockPayment;
use App\Support\Payments\SettleRegistrationFee;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\ModelStates\Transition;

/**
 * Settles a pending attempt and advances its application past the fee gate in one
 * transaction, so a payment that is paid while its application still waits for the fee is
 * impossible.
 *
 * Reachable only from Thawani (server-verified provider evidence) and cash (an authorized
 * staff confirmation) — bank transfer never lands here directly, it must pass through
 * `AwaitingVerification` first. `SettleRegistrationFee` enforces that the supplied evidence's
 * method matches the payment's own method, so this transition cannot be driven evidence-free
 * or with evidence built for a different method.
 *
 * Returns the payment as it ended up. Normally Paid, but **Failed** if a second successful
 * charge was detected; see `SettleRegistrationFee`. Callers must read the returned state
 * rather than assume success.
 */
class PendingToPaid extends Transition
{
    /**
     * `$evidence` is nullable only because Spatie instantiates the transition with the model
     * alone when answering `canTransitionTo()` — a required parameter would turn every
     * visibility check (Filament included) into a fatal `ArgumentCountError`. The guard in
     * `handle()`, not the signature, is what makes this transition evidence-required.
     */
    public function __construct(
        public Payment $payment,
        public ?PaymentSettlementEvidence $evidence = null,
        public ?string $notes = null,
    ) {}

    public function handle(): Payment
    {
        if ($this->evidence === null) {
            throw new InvalidArgumentException('A payment cannot be settled without settlement evidence.');
        }

        // Bank transfer is deliberately excluded here even though its evidence's method would
        // match: it must always pass through AwaitingVerification first (see
        // AwaitingVerificationToPaid). Restricting by evidence *type*, not merely by method,
        // is what makes "Pending -> Paid directly" structurally unreachable for that method.
        if (! $this->evidence instanceof ThawaniSettlementEvidence && ! $this->evidence instanceof CashSettlementEvidence) {
            throw InvalidSettlementEvidenceException::methodMismatch($this->payment, $this->evidence);
        }

        return DB::transaction(function () {
            // Application first, payment second — the project's lock order.
            $locked = LockPayment::inState($this->payment, Pending::class);

            return app(SettleRegistrationFee::class)->settle(
                $locked,
                $this->evidence,
                $this->notes,
            );
        }, attempts: 3);
    }
}
