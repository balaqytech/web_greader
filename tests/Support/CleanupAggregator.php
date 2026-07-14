<?php

namespace Tests\Support;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Test-only infrastructure: runs a list of independent teardown steps, attempting every one
 * regardless of whether an earlier step failed, and collects (rather than silently swallows)
 * any failures. Intended for integration-style tests whose teardown talks to an external
 * resource (e.g. a real database connection) where "one step throws" must never prevent the
 * remaining steps from at least being attempted.
 */
final class CleanupAggregator
{
    /** @var array<int, Throwable> */
    private array $errors = [];

    public function run(string $label, Closure $step): void
    {
        try {
            $step();
        } catch (Throwable $exception) {
            $this->errors[] = new RuntimeException("Cleanup step [{$label}] failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<int, Throwable>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Throw a single exception summarizing every collected cleanup failure. Call only after
     * every cleanup step has already been attempted. A no-op when nothing failed.
     */
    public function throwIfAny(): void
    {
        if (! $this->hasErrors()) {
            return;
        }

        $messages = array_map(fn (Throwable $exception) => $exception->getMessage(), $this->errors);

        throw new RuntimeException(
            count($this->errors).' cleanup step(s) failed: '.implode(' | ', $messages),
            0,
            $this->errors[0],
        );
    }
}
