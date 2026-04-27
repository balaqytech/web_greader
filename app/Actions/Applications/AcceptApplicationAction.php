<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\States\Applications\Accepted;

final class AcceptApplicationAction
{
    /**
     * Accept an application under review.
     * The transition handler creates the Guardian and Student records.
     */
    public function execute(Application $application, ?int $transitionedBy = null, ?string $notes = null): Application
    {
        $application->status->transitionTo(Accepted::class, transitionedBy: $transitionedBy, notes: $notes);

        return $application->fresh(['student', 'student.guardian']);
    }
}
