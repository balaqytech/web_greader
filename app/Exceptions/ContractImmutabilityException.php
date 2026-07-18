<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\ApplicationContract;
use RuntimeException;

/**
 * Thrown when code attempts to mutate a frozen field of an already-created contract version, or
 * to delete a version outright. A version's snapshot is the authoritative record of what was
 * generated and signed; only the lifecycle fields (signing artifacts, state, supersession
 * linkage, token) may change, and history is never erased.
 */
class ContractImmutabilityException extends RuntimeException
{
    public static function field(ApplicationContract $contract, string $column): self
    {
        return new self(sprintf(
            'Contract version %s field "%s" is immutable after creation and cannot be changed.',
            $contract->getKey() ?? '(new)',
            $column,
        ));
    }

    public static function deletionForbidden(): self
    {
        return new self('Contract versions are part of the admission record and cannot be deleted.');
    }
}
