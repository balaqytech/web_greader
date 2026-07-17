<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\ApplicationDocument;
use Illuminate\Support\Collection;

/**
 * One logical requirement in a requirement summary.
 *
 * A logical requirement usually maps to a single {@see ApplicationDocument}, but the identity
 * group folds the civil-ID and passport rows into one: either member being present satisfies
 * the whole group, which is why presence is evaluated across a collection of member documents
 * rather than a single row.
 *
 * @property-read Collection<int, ApplicationDocument> $members
 */
final class LogicalRequirement
{
    /**
     * @param  Collection<int, ApplicationDocument>  $members
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $isRequired,
        public readonly bool $isSatisfied,
        public readonly Collection $members,
    ) {}

    /**
     * A required requirement that is not satisfied is a warning — never a hard block.
     */
    public function isWarning(): bool
    {
        return $this->isRequired && ! $this->isSatisfied;
    }
}
