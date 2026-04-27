<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\States\Applications\Rejected;

final class RejectApplicationAction
{
    /**
     * Reject an application under review with a reason.
     */
    public function execute(Application $application, ?string $rejectionReason = null, ?int $transitionedBy = null): Application
    {
        $application->status->transitionTo(
            Rejected::class,
            rejectionReason: $rejectionReason,
            transitionedBy: $transitionedBy,
        );

        return $application->fresh();
    }
}
