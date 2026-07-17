<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Actions\Applications\RenderApplicationContractAction;
use App\DTOs\Contracts\ContractSnapshot;
use App\Models\Application;

/**
 * The single authoritative snapshot builder (§3.5, §6.2). Given an application in its current
 * persisted state, it resolves:
 *
 *   - the confirmed contract-relevant minimum set (student legal name / civil number, guardian
 *     legal name / identity, branch id+name, program id+name) — always compared during
 *     correction classification even when the template omits a field;
 *   - the exact placeholder values rendered into the template;
 *   - the fully-resolved `rendered_body` (frozen once, never regenerated for this version);
 *   - the SHA-256 `template_hash` of the exact source template.
 *
 * Callers that persist the result into a version must build it against a freshly locked
 * application so the snapshot reflects committed data, not a stale pre-lock read.
 */
final class BuildContractSnapshotAction
{
    public function __construct(
        private readonly RenderApplicationContractAction $renderer,
    ) {}

    public function handle(Application $application): ContractSnapshot
    {
        $template = $application->program->contract;
        $variables = $this->renderer->variables($application);

        return new ContractSnapshot(
            minimum: ContractSnapshot::normalizeMap($this->minimumFields($application)),
            placeholders: ContractSnapshot::normalizeMap($variables),
            renderedBody: $this->renderer->render($template, $variables),
            templateHash: $this->hashTemplate($template),
            backfilled: false,
        );
    }

    /**
     * SHA-256 of the exact source template. A null template (no contract text configured) still
     * hashes deterministically, so two applications on the same empty-template program compare
     * equal rather than throwing.
     */
    public function hashTemplate(?string $template): string
    {
        return hash('sha256', $template ?? '');
    }

    /**
     * The confirmed-minimum contract-relevant set (§3.5). Kept independent of the template's
     * placeholders on purpose: a change to any of these is contract-relevant even if the
     * current template never prints it.
     *
     * @return array<string, mixed>
     */
    private function minimumFields(Application $application): array
    {
        return [
            'student_name' => $application->student_name,
            'student_civil_number' => $application->student_civil_number,
            'guardian_name' => $application->guardian_name,
            'guardian_id_number' => $application->guardian_id_number,
            'branch_id' => $application->branch_id,
            'branch_name' => $application->branch?->name,
            'program_id' => $application->program_id,
            'program_name' => $application->program?->name,
        ];
    }
}
