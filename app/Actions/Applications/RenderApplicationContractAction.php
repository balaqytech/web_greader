<?php

namespace App\Actions\Applications;

use App\Models\Application;

/**
 * Renders the contract body for an application. The acting guardian (father, mother, or
 * relative) is resolved through the single `guardian_name` domain accessor so every caller
 * renders the same signer. Callers that are about to bind a signature to this text (as
 * opposed to merely displaying it) must call this against a freshly locked `Application`
 * instance so the rendered body reflects the current persisted data, not a stale read taken
 * before a row lock was acquired.
 */
final class RenderApplicationContractAction
{
    public function execute(Application $application): string
    {
        return $this->parseContract($application->program->contract, [
            'program_name' => $application->program->name,
            'parent_name' => $application->guardian_name,
            'student_name' => $application->student_name,
            'enrollment_date' => $application->created_at->format('d/m/Y'),
            'branch_price' => $application->program->branchPrice($application->branch),
        ]);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function parseContract(?string $template, array $variables): string
    {
        $template ??= '';

        foreach ($variables as $key => $value) {
            $template = str_replace('$'.$key.'$', (string) $value, $template);
        }

        return $template;
    }
}
