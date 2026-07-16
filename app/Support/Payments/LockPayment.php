<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Exceptions\StalePaymentStateException;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;

/**
 * Serialization primitive for state-changing payment operations, and the single owner of the
 * project's payment lock order.
 *
 * **Application first, payment second — always.** Every path that resolves a payment can also
 * advance its application past the fee gate, so both rows are taken. Two concurrent attempts
 * on one application that grabbed these in opposite orders would deadlock; taking them in one
 * fixed order everywhere means the loser simply waits and then observes the winner's result.
 * The order is enforced here rather than documented and left to callers, because a caller
 * that forgets is a deadlock in production, not a test failure.
 *
 * Locking the application also serialises the invariants no database constraint expresses in
 * this project (see the payments migration): at most one active attempt, and at most one paid
 * attempt, per application.
 *
 * Both rows are re-read ignoring BranchScope so they are always found — the scope is a
 * presentation concern and must never hide a row whose invariant we are about to enforce.
 * Authorization is the caller's job and happens before this.
 */
final class LockPayment
{
    /**
     * Locks the payment's application, then the payment, and verifies the payment's
     * persisted state before any write. A caller whose in-memory state is stale — because
     * another request already resolved this attempt — is rejected rather than allowed to
     * apply a second outcome.
     *
     * @param  class-string  $expectedState
     *
     * @throws StalePaymentStateException
     */
    public static function inState(Payment $payment, string $expectedState): LockedPayment
    {
        $application = Application::withoutGlobalScope(BranchScope::class)
            ->whereKey($payment->application_id)
            ->lockForUpdate()
            ->first();

        $fresh = Payment::withoutGlobalScope(BranchScope::class)
            ->whereKey($payment->getKey())
            ->lockForUpdate()
            ->first();

        if ($application === null || $fresh === null || ! $fresh->status instanceof $expectedState) {
            throw StalePaymentStateException::make(
                $payment,
                $expectedState,
                $fresh?->status,
            );
        }

        return new LockedPayment($application, $fresh);
    }

    /**
     * Locks an application for the duration of a payment decision without requiring an
     * existing payment — used when creating an attempt, where the "one active attempt"
     * invariant must hold across concurrent creators.
     */
    public static function application(Application $application): Application
    {
        return Application::withoutGlobalScope(BranchScope::class)
            ->whereKey($application->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
