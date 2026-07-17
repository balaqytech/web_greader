<?php

namespace App\Actions\Applications;

use App\Models\Application;

/**
 * Resolves the contract template's placeholder variables and renders the body. The acting
 * guardian (father, mother, or relative) is resolved through the single `guardian_name` domain
 * accessor so every caller renders the same signer.
 *
 * This produces the *live* body from *current* data. It is used only at generation time, by
 * BuildContractSnapshotAction, to freeze the result into the version's immutable `rendered_body`.
 * Display and signing never call this — they replay the stored `rendered_body` so a later
 * template or data change can never retroactively alter what a version says.
 */
final class RenderApplicationContractAction
{
    public function execute(Application $application): string
    {
        return $this->render($application->program->contract, $this->variables($application));
    }

    /**
     * The placeholder variable set resolved into the template for this application.
     *
     * @return array<string, mixed>
     */
    public function variables(Application $application): array
    {
        return [
            'program_name' => $application->program->name,
            'parent_name' => $application->guardian_name,
            'student_name' => $application->student_name,
            'enrollment_date' => $application->created_at->format('d/m/Y'),
            'branch_price' => $application->program->branchPrice($application->branch),
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(?string $template, array $variables): string
    {
        $template ??= '';

        foreach ($variables as $key => $value) {
            $template = str_replace('$'.$key.'$', (string) $value, $template);
        }

        return $template;
    }
}
