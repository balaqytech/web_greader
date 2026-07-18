<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\Models\ApplicationActivity;
use Illuminate\Support\Facades\Auth;

final class RecordApplicationActivityAction
{
    /**
     * Records an activity row. `$actorId` lets a caller pin the acting user explicitly (the
     * correction workflow does this — it never trusts ambient Auth); when omitted it falls back
     * to the authenticated user, preserving the behaviour of the system-driven transitions.
     */
    public function handle(
        Application $application,
        string $fromState,
        string $toState,
        ?string $notes = null,
        ?int $actorId = null,
    ): ApplicationActivity {
        return $application->activities()->create([
            'transitioned_by' => $actorId ?? Auth::id(),
            'from_state' => $fromState,
            'to_state' => $toState,
            'notes' => $notes,
            'transitioned_at' => now(),
        ]);
    }
}
