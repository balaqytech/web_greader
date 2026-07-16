<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Projects an allowlisted application context through already-authorized payment rows.
 *
 * Application ids are always derived from `payments.application_id`. The projection never
 * accepts an arbitrary application id and never returns an Application model, so central
 * finance gains the context needed to verify a payment without gaining general application
 * access.
 */
class PaymentApplicationProjection
{
    public const APPLICATION_REFERENCE = 'projected_application_reference';

    public const STUDENT_NAME = 'projected_student_name';

    public const PROGRAM_NAME = 'projected_program_name';

    public const BRANCH_NAME = 'projected_branch_name';

    /**
     * Adds scalar subqueries to an existing BranchScope-filtered Payment query.
     */
    public function apply(Builder $payments, bool $allowScopedBranchUser = false): Builder
    {
        $this->authorize(Auth::user(), $allowScopedBranchUser);

        $connection = $payments->getModel()->getConnection();

        return $payments->addSelect([
            self::APPLICATION_REFERENCE => $connection->table('applications')
                ->select('ref_no')
                ->whereColumn('applications.id', 'payments.application_id')
                ->limit(1),
            self::STUDENT_NAME => $connection->table('applications')
                ->select('student_name')
                ->whereColumn('applications.id', 'payments.application_id')
                ->limit(1),
            self::PROGRAM_NAME => $connection->table('applications')
                ->join('programs', 'programs.id', '=', 'applications.program_id')
                ->select('programs.name')
                ->whereColumn('applications.id', 'payments.application_id')
                ->limit(1),
            self::BRANCH_NAME => $connection->table('applications')
                ->join('branches', 'branches.id', '=', 'applications.branch_id')
                ->select('branches.name')
                ->whereColumn('applications.id', 'payments.application_id')
                ->limit(1),
        ]);
    }

    /**
     * @return Collection<int, PaymentApplicationProjectionRow>
     */
    public function current(): Collection
    {
        return $this->apply(Payment::query()->forRegistrationFee())
            ->get()
            ->map(fn (Payment $payment): PaymentApplicationProjectionRow => new PaymentApplicationProjectionRow(
                paymentReference: (string) $payment->reference,
                applicationReference: (string) $payment->getAttribute(self::APPLICATION_REFERENCE),
                studentName: (string) $payment->getAttribute(self::STUDENT_NAME),
                programName: (string) $payment->getAttribute(self::PROGRAM_NAME),
                branchName: (string) $payment->getAttribute(self::BRANCH_NAME),
                feeAmount: (string) $payment->amount,
                currency: (string) $payment->currency,
            ))
            ->values();
    }

    /**
     * Cross-branch projection keeps the approved conjunction. Ordinary branch users can use
     * the same projection for their already-scoped rows when they hold View:Payment.
     */
    private function authorize(?AuthUser $user, bool $allowScopedBranchUser = false): void
    {
        if ($user === null) {
            throw new AuthorizationException('This user is not authorized to project application data through payments.');
        }

        $canRead = $user->can('View:Payment') || $user->can('VerifyBankTransfer:Payment');
        $canProject = $user->can('ViewAllBranches:Payment') && $canRead;

        if ($allowScopedBranchUser && ! $user->can('ViewAllBranches:Payment')) {
            $canProject = $user->can('View:Payment');
        }

        if (! $canProject) {
            throw new AuthorizationException('This user is not authorized to project application data through payments.');
        }
    }
}
