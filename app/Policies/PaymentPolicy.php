<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PaymentMethod;
use App\Models\Application;
use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Pending;
use App\Support\Authorization\BranchAccess;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Record-level authorization for payments, paired with BranchScope at the query level —
 * both defer to BranchAccess so they cannot diverge.
 *
 * Central finance reaches other branches' payments through `ViewAllBranches:Payment`, which
 * BranchAccess already honours; there is no separate cross-branch branch here.
 *
 * Abilities for initiating an attempt and uploading a receipt are added by the phases that
 * implement those flows, rather than guessed at here.
 */
class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Payment');
    }

    public function view(AuthUser $authUser, Payment $payment): bool
    {
        return $this->authorizeForBranch($authUser, $payment, 'View:Payment');
    }

    /**
     * Initiating an attempt (staff or chatbot) happens before any Payment row exists, so this
     * is checked against the *application* it would belong to rather than a payment instance.
     */
    public function create(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Create:Payment')
            && BranchAccess::canAccessBranch($authUser, Payment::class, $application->branch_id);
    }

    /**
     * Verifying or rejecting a bank receipt is one decision with two outcomes, so both are
     * gated by the same permission. Only meaningful for a bank transfer that is actually
     * awaiting verification — permitting it on any other method or state would be a way to
     * mark a fee paid without a receipt ever having been reviewed.
     */
    public function verifyBankTransfer(AuthUser $authUser, Payment $payment): bool
    {
        return $this->authorizeForBranch($authUser, $payment, 'VerifyBankTransfer:Payment')
            && $payment->method === PaymentMethod::BANK_TRANSFER
            && $payment->status instanceof AwaitingVerification;
    }

    /**
     * Confirming cash marks a fee paid with no verifiable money movement behind it, so it
     * carries its own permission that no ordinary role holds — including central finance.
     * Restricted to a cash attempt that is still pending: a terminal attempt must never be
     * revived, and a Thawani or bank attempt must never be settled by this route.
     */
    public function confirmCash(AuthUser $authUser, Payment $payment): bool
    {
        return $this->authorizeForBranch($authUser, $payment, 'ConfirmCash:Payment')
            && $payment->method === PaymentMethod::CASH
            && $payment->status instanceof Pending;
    }

    private function authorizeForBranch(AuthUser $authUser, Payment $payment, string $permission): bool
    {
        return $authUser->can($permission)
            && BranchAccess::canAccessBranch($authUser, Payment::class, $payment->branch_id);
    }
}
