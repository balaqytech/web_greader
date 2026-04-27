<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\States\Applications\PendingRegistration;

final class ReturnApplicationForCorrectionAction
{
    /**
     * Return an application under review back to pending registration for corrections.
     */
    public function execute(Application $application, ?string $notes = null, ?int $transitionedBy = null): Application
    {
        $application->status->transitionTo(
            PendingRegistration::class,
            notes: $notes,
            transitionedBy: $transitionedBy,
        );

        return $application->fresh();
    }
}
