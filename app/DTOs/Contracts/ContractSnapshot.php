<?php

declare(strict_types=1);

namespace App\DTOs\Contracts;

/**
 * Immutable snapshot of a single contract version. Together with the version's `rendered_body`
 * and `template_hash`, it is the authoritative, frozen record of "what the signer saw" and the
 * basis for correction classification (§6.2).
 *
 * `minimum` holds the confirmed contract-relevant field set (§3.5) — always compared during
 * classification, independent of whichever placeholders the template happens to use.
 * `placeholders` holds the exact variable values resolved into the template at generation.
 * Both are stably normalized (scalars cast to string/null, strings trimmed) and
 * deterministically key-ordered so byte-for-byte comparison is meaningful.
 *
 * @phpstan-type SnapshotData array{minimum: array<string, ?string>, placeholders: array<string, ?string>, meta: array{backfilled: bool}}
 */
final readonly class ContractSnapshot
{
    /**
     * @param  array<string, ?string>  $minimum
     * @param  array<string, ?string>  $placeholders
     */
    public function __construct(
        public array $minimum,
        public array $placeholders,
        public string $renderedBody,
        public string $templateHash,
        public bool $backfilled = false,
    ) {}

    /**
     * Reconstruct a snapshot from a persisted `data_snapshot` column plus the version's stored
     * `rendered_body`/`template_hash`. Tolerates the legacy/backfilled shape.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromStored(array $data, string $renderedBody, string $templateHash): self
    {
        /** @var array<string, ?string> $minimum */
        $minimum = self::normalizeMap($data['minimum'] ?? []);
        /** @var array<string, ?string> $placeholders */
        $placeholders = self::normalizeMap($data['placeholders'] ?? []);
        $backfilled = (bool) (($data['meta']['backfilled'] ?? false));

        return new self($minimum, $placeholders, $renderedBody, $templateHash, $backfilled);
    }

    /**
     * The persisted `data_snapshot` shape. `meta.backfilled` is the only field classification
     * ignores when diffing — every other value is authoritative.
     *
     * @return SnapshotData
     */
    public function toArray(): array
    {
        return [
            'minimum' => $this->minimum,
            'placeholders' => $this->placeholders,
            'meta' => ['backfilled' => $this->backfilled],
        ];
    }

    /**
     * Stable scalar normalization with deterministic key ordering: every value becomes a
     * trimmed string or null, and keys are sorted, so two snapshots of identical data always
     * serialize identically.
     *
     * @return array<string, ?string>
     */
    public static function normalizeMap(mixed $map): array
    {
        if (! is_array($map)) {
            return [];
        }

        $normalized = [];

        foreach ($map as $key => $value) {
            $normalized[(string) $key] = self::normalizeScalar($value);
        }

        ksort($normalized);

        return $normalized;
    }

    private static function normalizeScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
