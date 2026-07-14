<?php

namespace App\States\Applications\Transitions;

use App\States\Applications\CorrectionRequested;

/**
 * Cancellation from CorrectionRequested. Registered in Phase 0 because cancelling does not
 * depend on correction persistence/classification (which arrive later); only the entry
 * edges into CorrectionRequested are deferred.
 */
class CorrectionRequestedToCancelled extends CancelApplicationTransition
{
    protected function fromState(): string
    {
        return CorrectionRequested::class;
    }
}
