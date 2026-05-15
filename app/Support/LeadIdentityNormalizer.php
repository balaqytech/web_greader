<?php

namespace App\Support;

use Normalizer;

final class LeadIdentityNormalizer
{
    /**
     * Normalize Arabic/Latin student names for comparison.
     */
    public function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        if (class_exists(Normalizer::class) && Normalizer::isNormalized($name, Normalizer::FORM_KC) === false) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_KC);

            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        $name = preg_replace('/\x{0640}/u', '', $name) ?? $name;
        $name = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $name) ?? $name;
        $name = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $name);
        $name = str_replace('ى', 'ي', $name);
        $name = str_replace('ة', 'ه', $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * @return list<string>
     */
    public function tokenize(string $name): array
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $normalized), fn (string $token) => $token !== ''));
    }

    /**
     * Deterministic identity fingerprint for DB uniqueness.
     */
    public function fingerprint(
        string $whatsapp,
        int $programId,
        int $seasonId,
        ?int $branchId,
        string $studentName,
    ): string {
        $tokens = $this->tokenize($studentName);

        $payload = implode('|', [
            $whatsapp,
            (string) $programId,
            (string) $seasonId,
            $branchId === null ? '' : (string) $branchId,
            implode('|', $tokens),
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Whether shorter tokens form a strict prefix of longer tokens (same order).
     *
     * @param  list<string>  $shorter
     * @param  list<string>  $longer
     */
    public function isTokenPrefix(array $shorter, array $longer): bool
    {
        if ($shorter === [] || count($shorter) >= count($longer)) {
            return false;
        }

        foreach ($shorter as $index => $token) {
            if (! isset($longer[$index]) || $longer[$index] !== $token) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pick the more complete display name (more tokens, then longer string).
     */
    public function preferLongerDisplayName(string $existing, string $incoming): string
    {
        $existingTokens = $this->tokenize($existing);
        $incomingTokens = $this->tokenize($incoming);

        if (count($incomingTokens) > count($existingTokens)) {
            return trim($incoming);
        }

        if (count($incomingTokens) < count($existingTokens)) {
            return trim($existing);
        }

        return mb_strlen($incoming) > mb_strlen($existing) ? trim($incoming) : trim($existing);
    }
}
