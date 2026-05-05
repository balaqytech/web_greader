<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\Models\ApplicationActivity;
use Illuminate\Support\Facades\Auth;

final class RecordApplicationActivityAction
{
    public function handle(
        Application $application,
        string $fromState,
        string $toState,
        ?string $notes = null,
    ): ApplicationActivity {
        return $application->activities()->create([
            'transitioned_by' => Auth::id(),
            'from_state' => $fromState,
            'to_state' => $toState,
            'notes' => $notes,
            'transitioned_at' => now(),
        ]);
    }
}
