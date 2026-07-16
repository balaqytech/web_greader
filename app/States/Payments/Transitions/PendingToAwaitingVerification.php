<?php

declare(strict_types=1);

namespace App\States\Payments\Transitions;

use App\Enums\PaymentMethod;
use App\Exceptions\InvalidSettlementEvidenceException;
use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Pending;
use App\Support\Payments\LockPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\ModelStates\Transition;

/**
 * A bank-transfer receipt was uploaded. Bank transfer only — Thawani settles directly from
 * provider evidence, and cash is confirmed in person, so neither ever has a "waiting on a
 * human to check a document" state to pass through.
 *
 * The application is untouched: it stays at the fee gate until central finance verifies the
 * receipt.
 */
class PendingToAwaitingVerification extends Transition
{
    /**
     * `$receiptPath` is nullable only because Spatie instantiates the transition with the
     * model alone when answering `canTransitionTo()` — see `PendingToPaid` for why. The guard
     * in `handle()` is the real enforcement.
     */
    public function __construct(
        public Payment $payment,
        public ?string $receiptPath = null,
    ) {}

    public function handle(): Payment
    {
        if ($this->receiptPath === null || trim($this->receiptPath) === '') {
            throw new InvalidArgumentException('A bank-transfer receipt upload must supply a stored receipt path.');
        }

        return DB::transaction(function () {
            $locked = LockPayment::inState($this->payment, Pending::class);
            $payment = $locked->payment;

            if ($payment->method !== PaymentMethod::BANK_TRANSFER) {
                throw InvalidSettlementEvidenceException::methodNotEligible($payment, PaymentMethod::BANK_TRANSFER);
            }

            $payment->receipt_path = $this->receiptPath;
            $payment->status = AwaitingVerification::class;
            $payment->save();

            return $payment;
        }, attempts: 3);
    }
}
