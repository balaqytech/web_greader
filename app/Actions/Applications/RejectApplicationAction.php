<?php

namespace App\Actions\Applications;

use App\Models\Application;
use Illuminate\Support\Facades\DB;

class RejectApplicationAction
{
    public function handle(Application $application, string $reason): Application
    {
        return DB::transaction(function () use ($application, $reason) {
            $application->update([
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ]);

            return $application;
        });
    }
}
