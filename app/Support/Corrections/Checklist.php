<?php

declare(strict_types=1);

namespace App\Support\Corrections;

use App\Exceptions\CorrectionException;

/**
 * The checklist shape and its rules in one place, shared by the request and completion paths
 * (and reusable by the Phase 5 API) so the "distinct nonblank items, all start incomplete,
 * completion by index only, every item required" rules cannot be implemented two ways.
 */
final class Checklist
{
    /**
     * Build a fresh incomplete checklist from raw item strings: each is trimmed, blanks are
     * dropped, duplicates are collapsed, and at least one must remain.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array{item: string, done: bool}>
     *
     * @throws CorrectionException
     */
    public static function fromItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $trimmed = trim((string) $item);

            if ($trimmed === '' || in_array($trimmed, $normalized, true)) {
                continue;
            }

            $normalized[] = $trimmed;
        }

        if ($normalized === []) {
            throw CorrectionException::checklistRequired();
        }

        return array_map(fn (string $item): array => ['item' => $item, 'done' => false], $normalized);
    }

    /**
     * Mark the given item indexes done. Completion accepts indexes only — never replacement
     * text — so the original items cannot be rewritten while being closed.
     *
     * @param  array<int, array{item: string, done: bool}>  $checklist
     * @param  array<int, int|string>  $completedIndexes
     * @return array<int, array{item: string, done: bool}>
     */
    public static function markCompleted(array $checklist, array $completedIndexes): array
    {
        $indexes = array_map('intval', $completedIndexes);

        foreach ($checklist as $index => $entry) {
            if (in_array($index, $indexes, true)) {
                $checklist[$index]['done'] = true;
            }
        }

        return $checklist;
    }

    /**
     * @param  array<int, array{item: string, done: bool}>  $checklist
     */
    public static function allDone(array $checklist): bool
    {
        foreach ($checklist as $entry) {
            if (! ($entry['done'] ?? false)) {
                return false;
            }
        }

        return $checklist !== [];
    }
}
