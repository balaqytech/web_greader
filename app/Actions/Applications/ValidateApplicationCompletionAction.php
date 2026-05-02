<?php

namespace App\Actions\Applications;

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;

class ValidateApplicationCompletionAction
{
    public function handle(Application $application): void
    {
        $application->loadMissing(['applicationStudent', 'contacts']);

        if (! $application->applicationStudent) {
            throw new ApplicationIncompleteException('Student information is missing.');
        }

        if (! $application->applicationStudent->name) {
            throw new ApplicationIncompleteException('Student name is required.');
        }

        if (! $application->applicationStudent->civil_number) {
            throw new ApplicationIncompleteException('Student civil number is required.');
        }

        if (! $application->program_id || ! $application->branch_id || ! $application->season_id) {
            throw new ApplicationIncompleteException('Program, branch, and season are required.');
        }

        $guardianContacts = $application->contacts()
            ->where('is_guardian', true)
            ->get();

        if ($guardianContacts->count() !== 1) {
            throw new ApplicationIncompleteException('Exactly one guardian contact is required.');
        }

        $guardian = $guardianContacts->first();

        if (! $guardian->name || ! $guardian->phone || ! $guardian->id_number) {
            throw new ApplicationIncompleteException('Guardian must have a name, phone, and ID number.');
        }

        $nonGuardianCount = $application->contacts()
            ->where('is_guardian', false)
            ->count();

        if ($nonGuardianCount < 2) {
            throw new ApplicationIncompleteException('At least two non-guardian emergency contacts are required.');
        }
    }
}
