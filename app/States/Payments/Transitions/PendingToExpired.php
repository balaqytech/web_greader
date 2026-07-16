<?php

declare(strict_types=1);

namespace App\States\Payments\Transitions;

use App\Models\Payment;
use App\States\Payments\Expired;
use App\States\Payments\Pending;
use App\Support\Payments\LockPayment;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * The provider's checkout window closed before the attempt was completed. Distinct from
 * Failed: nothing was declined, the session simply lapsed.
 *
 * Only ever driven by the provider confirming the session is expired — never by our own
 * clock reading a stored `provider_expires_at`. A session we believe has lapsed may have
 * been paid in its final seconds, and only the provider knows.
 *
 * The application is untouched; the guardian may start a fresh attempt.
 */
class PendingToExpired extends Transition
{
    /**
     * @param  array<string, mixed>|null  $providerPayload  Sanitised provider evidence.
     */
    public function __construct(
        public Payment $payment,
        public ?array $providerPayload = null,
    ) {}

    public function handle(): Payment
    {
        return DB::transaction(function () {
            $locked = LockPayment::inState($this->payment, Pending::class);

            $payment = $locked->payment;
            $payment->status = Expired::class;

            if ($this->providerPayload !== null) {
                $payment->provider_payload = $this->providerPayload;
            }

            $payment->save();

            return $payment;
        }, attempts: 3);
    }
}
