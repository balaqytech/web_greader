<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The only way central finance sees application context alongside a payment, without ever
 * being granted `ViewAllBranches:Application` or an unscoped `Application` query of its own.
 *
 * The application ids this reads are derived exclusively from Payment rows the current
 * principal can already see — the query below is `Payment`'s own `BranchScope`-filtered view
 * (which inspects the authenticated user, exactly as every other payment query in this
 * project does), never `withoutGlobalScopes()`. There is deliberately no parameter for an
 * application id or a target user: accepting one would turn this from a projection *through*
 * an authorized payment view into a second, looser door into Application data.
 *
 * Returns plain rows — reference, student name, program, branch, fee amount — never the
 * `Application` model itself, and never reached via `$payment->application`, so a consumer
 * cannot pull in a field this projection did not choose to expose.
 */
class PaymentApplicationProjection
{
    /**
     * @return Collection<int, PaymentApplicationProjectionRow>
     *
     * @throws AuthorizationException
     */
    public function current(): Collection
    {
        $this->authorize(Auth::user());

        // BranchScope already applied via the authenticated principal — this is exactly the
        // set of payments the caller is allowed to see, nothing wider.
        $payments = Payment::query()
            ->forRegistrationFee()
            ->get(['id', 'application_id', 'amount', 'currency']);

        $latestPerApplication = $payments
            ->groupBy('application_id')
            ->map(fn (Collection $group): Payment => $group->sortByDesc('id')->first());

        $applications = Application::withoutGlobalScopes()
            ->whereIn('id', $latestPerApplication->keys())
            ->with(['program', 'branch'])
            ->get()
            ->keyBy('id');

        return $latestPerApplication
            ->map(function (Payment $payment) use ($applications): ?PaymentApplicationProjectionRow {
                $application = $applications->get($payment->application_id);

                if ($application === null) {
                    return null;
                }

                return new PaymentApplicationProjectionRow(
                    applicationReference: (string) $application->ref_no,
                    studentName: (string) $application->student_name,
                    programName: (string) ($application->program?->name ?? ''),
                    branchName: (string) ($application->branch?->name ?? ''),
                    feeAmount: (string) $payment->amount,
                    currency: (string) $payment->currency,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(?AuthUser $user): void
    {
        $canProject = $user !== null
            && $user->can('ViewAllBranches:Payment')
            && ($user->can('View:Payment') || $user->can('VerifyBankTransfer:Payment'));

        if (! $canProject) {
            throw new AuthorizationException('This user is not authorized to project application data through payments.');
        }
    }
}
