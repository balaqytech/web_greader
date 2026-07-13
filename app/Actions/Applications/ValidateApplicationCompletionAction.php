<?php

namespace App\Actions\Applications;

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;

/**
 * Validates that an application holds the minimum flat-schema data required to
 * generate a contract (§3.5): student identity plus a resolvable guardian.
 * Real implementation replacing the missing class referenced by the retired
 * Draft->Submitted path (C7).
 */
final class ValidateApplicationCompletionAction
{
    /**
     * @throws ApplicationIncompleteException
     */
    public function handle(Application $application): void
    {
        if (blank($application->student_name)) {
            throw new ApplicationIncompleteException(__('alerts.application.student_name_required'));
        }

        if (blank($application->student_civil_number)) {
            throw new ApplicationIncompleteException(__('alerts.application.student_civil_number_required'));
        }

        [$guardianName, $guardianIdNumber] = $this->resolveGuardianIdentity($application);

        if (blank($guardianName) || blank($guardianIdNumber)) {
            throw new ApplicationIncompleteException(__('alerts.application.guardian_required'));
        }
    }

    /**
     * @return array{0: ?string, 1: ?string} [name, id_number] of the acting guardian
     */
    private function resolveGuardianIdentity(Application $application): array
    {
        if ($application->father_is_guardian) {
            return [$application->father_name, $application->father_id_number];
        }

        if ($application->mother_is_guardian) {
            return [$application->mother_name, $application->mother_id_number];
        }

        return [$application->relative_name, $application->relative_id_number];
    }
}
