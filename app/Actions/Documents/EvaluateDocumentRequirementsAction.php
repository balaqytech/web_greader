<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\States\Documents\Approved;
use App\States\Documents\Missing;
use App\States\Documents\Rejected;
use App\States\Documents\Uploaded;
use App\Support\Documents\DocumentRequirementSummary;
use App\Support\Documents\LogicalRequirement;
use Illuminate\Support\Collection;

/**
 * Reduces an application's document rows to a structured, presentation-agnostic summary of
 * logical requirements. Read-only: it writes nothing and takes no locks.
 *
 * Presence satisfies a requirement. A document in {@see Uploaded} or {@see Approved} is
 * present; {@see Missing} and {@see Rejected} are
 * not, so a rejected document warns exactly like a missing one.
 *
 * Rows sharing a `requirement_group` collapse into one logical requirement satisfied by *any*
 * present member — that is how either the civil ID or the passport satisfies the single
 * `student_identity` requirement. Ungrouped rows are each their own logical requirement.
 *
 * Only required rows appear in the summary; an optional transfer file (a student no longer
 * transferring) is excluded and can never produce a warning.
 */
final class EvaluateDocumentRequirementsAction
{
    public function execute(Application $application): DocumentRequirementSummary
    {
        $documents = $application->documents()
            ->where('is_required', true)
            ->get();

        $requirements = $documents
            ->groupBy(fn (ApplicationDocument $document): string => $this->logicalKey($document))
            ->map(fn (Collection $members, string $key): LogicalRequirement => new LogicalRequirement(
                key: $key,
                label: $this->label($members),
                isRequired: true,
                isSatisfied: $members->contains(fn (ApplicationDocument $document): bool => $this->isPresent($document)),
                members: $members->values(),
            ))
            ->values();

        return new DocumentRequirementSummary($requirements);
    }

    private function logicalKey(ApplicationDocument $document): string
    {
        return $document->requirement_group ?? $document->type->value;
    }

    /**
     * @param  Collection<int, ApplicationDocument>  $members
     */
    private function label(Collection $members): string
    {
        $group = $members->first()->requirement_group;

        if ($group !== null) {
            return __("admin.document.groups.{$group}");
        }

        return $members->first()->type->getLabel();
    }

    private function isPresent(ApplicationDocument $document): bool
    {
        return $document->status instanceof Uploaded
            || $document->status instanceof Approved;
    }
}
