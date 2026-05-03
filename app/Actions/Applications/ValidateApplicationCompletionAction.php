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
            throw new ApplicationIncompleteException(__('alerts.application.application_student_is_required'));
        }

        if (! $application->applicationStudent->name) {
            throw new ApplicationIncompleteException(__('alerts.application.application_student_name_is_required'));
        }

        if (! $application->applicationStudent->civil_number) {
            throw new ApplicationIncompleteException(__('alerts.application.application_student_civil_number_is_required'));
        }

        if (! $application->program_id || ! $application->branch_id || ! $application->season_id) {
            throw new ApplicationIncompleteException(__('alerts.application.program_branch_and_season_are_required'));
        }

        $guardianContacts = $application->contacts()
            ->where('is_guardian', true)
            ->get();

        if ($guardianContacts->count() !== 1) {
            throw new ApplicationIncompleteException(__('alerts.application.exactly_one_guardian_contact_is_required'));
        }

        $guardian = $guardianContacts->first();

        if (! $guardian->name || ! $guardian->phone || ! $guardian->id_number) {
            throw new ApplicationIncompleteException(__('alerts.application.guardian_must_have_name_phone_and_id_number'));
        }

        $nonGuardianCount = $application->contacts()
            ->where('is_guardian', false)
            ->count();

        if ($nonGuardianCount < 2) {
            throw new ApplicationIncompleteException(__('alerts.application.at_least_two_non_guardian_emergency_contacts_are_required'));
        }
    }
}
