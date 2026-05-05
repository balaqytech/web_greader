<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\Models\ApplicationContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateApplicationContractAction
{
    public function handle(Application $application): ApplicationContract
    {
        return DB::transaction(function () use ($application) {
            return $application->contract()->updateOrCreate(
                ['application_id' => $application->id],
                [
                    'token' => Str::random(64),
                    'token_expires_at' => now()->addDays(7),
                ]
            );
        });
    }
}
