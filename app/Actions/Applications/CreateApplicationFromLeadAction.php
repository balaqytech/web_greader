<?php

namespace App\Actions\Applications;

use App\Enums\ContactType;
use App\Models\Application;
use App\Models\Lead;
use App\States\Applications\Draft;
use Illuminate\Support\Facades\DB;

class CreateApplicationFromLeadAction
{
    public function handle(Lead $lead): Application
    {
        return DB::transaction(function () use ($lead) {
            $application = Application::create([
                'lead_id' => $lead->id,
                'program_id' => $lead->program_id,
                'branch_id' => $lead->branch_id,
                'status' => Draft::class,
            ]);

            $application->applicationStudent()->create([
                'name' => $lead->student_name,
            ]);

            $application->contacts()->create([
                'type' => ContactType::Father, // or whichever type the lead provides
                'relationship' => null,
                'name' => $lead->guardian_name,
                'phone' => $lead->guardian_phone,
                'is_guardian' => true,
            ]);

            return $application;
        });
    }
}
