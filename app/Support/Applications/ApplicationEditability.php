<?php

declare(strict_types=1);

namespace App\Support\Applications;

use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\CorrectionRequested;

/**
 * The single rule for whether an application's registration data may be edited, shared by the
 * policy (HTTP/Filament authorization) and the domain update action (re-checked under the row
 * lock). Keeping one implementation stops the presentation gate and the transactional guard
 * from drifting apart.
 *
 * Data is editable while it is still being assembled (registration-fee and data-completion
 * stages) and, additionally, while an open correction is being worked in `CorrectionRequested`.
 * Everywhere else — signed, under review, terminal, or `CorrectionRequested` with no open
 * correction — the record is immutable.
 */
final class ApplicationEditability
{
    public static function isEditable(Application $application): bool
    {
        if ($application->status instanceof AwaitingRegistrationFee
            || $application->status instanceof AwaitingApplicationCompletion) {
            return true;
        }

        return $application->status instanceof CorrectionRequested
            && $application->openCorrection()->exists();
    }
}
