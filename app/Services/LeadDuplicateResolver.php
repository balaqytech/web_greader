<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use App\Support\LeadIdentityNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class LeadDuplicateResolver
{
    private const int MIN_PREFIX_TOKENS = 3;

    public function __construct(
        private LeadIdentityNormalizer $normalizer,
    ) {}

    /**
     * Find an existing lead matching normalized identity or conservative token-prefix rules.
     */
    public function findExisting(
        string $whatsapp,
        string $studentName,
        int $programId,
        int $seasonId,
        ?int $branchId,
    ): ?Lead {
        $fingerprint = $this->normalizer->fingerprint(
            $whatsapp,
            $programId,
            $seasonId,
            $branchId,
            $studentName,
        );

        $exact = $this->baseQuery($whatsapp, $programId, $seasonId, $branchId)
            ->where('identity_fingerprint', $fingerprint)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        return $this->findPrefixMatch($whatsapp, $studentName, $programId, $seasonId, $branchId);
    }

    private function findPrefixMatch(
        string $whatsapp,
        string $studentName,
        int $programId,
        int $seasonId,
        ?int $branchId,
    ): ?Lead {
        $incomingTokens = $this->normalizer->tokenize($studentName);

        if ($incomingTokens === []) {
            return null;
        }

        /** @var Collection<int, Lead> $candidates */
        $candidates = $this->baseQuery($whatsapp, $programId, $seasonId, $branchId)->get();

        foreach ($candidates as $candidate) {
            $existingTokens = $this->normalizer->tokenize($candidate->student_name);

            if ($existingTokens === []) {
                continue;
            }

            if (count($incomingTokens) === count($existingTokens)) {
                if ($incomingTokens === $existingTokens) {
                    return $candidate;
                }

                continue;
            }

            $shorter = count($incomingTokens) < count($existingTokens) ? $incomingTokens : $existingTokens;
            $longer = count($incomingTokens) < count($existingTokens) ? $existingTokens : $incomingTokens;

            if (count($shorter) < self::MIN_PREFIX_TOKENS) {
                continue;
            }

            if ($this->normalizer->isTokenPrefix($shorter, $longer)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return Builder<Lead>
     */
    private function baseQuery(string $whatsapp, int $programId, int $seasonId, ?int $branchId)
    {
        return Lead::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('whatsapp', $whatsapp)
            ->where('program_id', $programId)
            ->where('season_id', $seasonId)
            ->when(
                $branchId === null,
                fn ($query) => $query->whereNull('branch_id'),
                fn ($query) => $query->where('branch_id', $branchId),
            );
    }
}
