<?php

declare(strict_types=1);

namespace App\Support\Documents;

use Illuminate\Support\Collection;

/**
 * The structured result of evaluating an application's document requirements.
 *
 * Presence — not approval — is what satisfies a requirement: an uploaded-but-unreviewed file
 * counts, an approved file counts, while a missing or rejected document does not. A rejected
 * document is therefore surfaced as an (unsatisfied) warning, exactly like a missing one.
 *
 * Nothing here blocks: {@see warnings()} exists so the contract-generation modal can list what
 * is outstanding, but the caller is free to proceed regardless.
 *
 * @property-read Collection<int, LogicalRequirement> $requirements
 */
final class DocumentRequirementSummary
{
    /**
     * @param  Collection<int, LogicalRequirement>  $requirements
     */
    public function __construct(
        public readonly Collection $requirements,
    ) {}

    /**
     * Required requirements that are not satisfied — the warnings a caller may surface.
     *
     * @return Collection<int, LogicalRequirement>
     */
    public function warnings(): Collection
    {
        return $this->requirements
            ->filter(fn (LogicalRequirement $requirement): bool => $requirement->isWarning())
            ->values();
    }

    public function hasWarnings(): bool
    {
        return $this->warnings()->isNotEmpty();
    }

    public function isComplete(): bool
    {
        return ! $this->hasWarnings();
    }
}
