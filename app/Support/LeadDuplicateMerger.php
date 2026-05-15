<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LeadDuplicateMerger
{
    public function __construct(
        private LeadIdentityNormalizer $normalizer,
    ) {}

    /**
     * Merge rows that share the same identity fingerprint within a scope.
     */
    public function mergeExactFingerprintDuplicates(): int
    {
        $merged = 0;

        $groups = DB::table('leads')
            ->select(
                'whatsapp',
                'program_id',
                'season_id',
                'branch_id',
                'identity_fingerprint',
                DB::raw('COUNT(*) as duplicate_count'),
            )
            ->whereNotNull('identity_fingerprint')
            ->groupBy('whatsapp', 'program_id', 'season_id', 'branch_id', 'identity_fingerprint')
            ->having('duplicate_count', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $leads = DB::table('leads')
                ->where('whatsapp', $group->whatsapp)
                ->where('program_id', $group->program_id)
                ->where('season_id', $group->season_id)
                ->where('identity_fingerprint', $group->identity_fingerprint)
                ->when(
                    $group->branch_id === null,
                    fn ($query) => $query->whereNull('branch_id'),
                    fn ($query) => $query->where('branch_id', $group->branch_id),
                )
                ->orderBy('id')
                ->get();

            $keepId = $this->pickKeepId($leads);

            foreach ($leads as $lead) {
                if ((int) $lead->id === $keepId) {
                    continue;
                }

                $this->mergeInto((int) $keepId, (int) $lead->id);
                $merged++;
            }
        }

        return $merged;
    }

    /**
     * @param  Collection<int, object>  $leads
     */
    private function pickKeepId(Collection $leads): int
    {
        return (int) $leads
            ->sortByDesc(fn (object $lead): array => [
                $this->canonicalScore((int) $lead->id, (string) $lead->student_name),
                -1 * (int) $lead->id,
            ])
            ->first()
            ->id;
    }

    private function canonicalScore(int $leadId, string $studentName): int
    {
        $score = 0;

        if (DB::table('applications')->where('lead_id', $leadId)->exists()) {
            $score += 100;
        }

        $score += DB::table('lead_contacts')->where('lead_id', $leadId)->count() * 10;
        $score += count($this->normalizer->tokenize($studentName));

        return $score;
    }

    private function mergeInto(int $keepId, int $duplicateId): void
    {
        DB::transaction(function () use ($keepId, $duplicateId): void {
            $keep = DB::table('leads')->where('id', $keepId)->first();
            $duplicate = DB::table('leads')->where('id', $duplicateId)->first();

            if (! $keep || ! $duplicate) {
                return;
            }

            DB::table('lead_contacts')
                ->where('lead_id', $duplicateId)
                ->update(['lead_id' => $keepId]);

            DB::table('applications')
                ->where('lead_id', $duplicateId)
                ->update(['lead_id' => $keepId]);

            $studentName = $this->normalizer->preferLongerDisplayName(
                (string) $keep->student_name,
                (string) $duplicate->student_name,
            );

            DB::table('leads')->where('id', $keepId)->update([
                'student_name' => $studentName,
                'student_name_normalized' => $this->normalizer->normalizeName($studentName),
                'identity_fingerprint' => $this->normalizer->fingerprint(
                    (string) $keep->whatsapp,
                    (int) $keep->program_id,
                    (int) $keep->season_id,
                    $keep->branch_id !== null ? (int) $keep->branch_id : null,
                    $studentName,
                ),
                'updated_at' => now(),
            ]);

            DB::table('leads')->where('id', $duplicateId)->delete();
        });
    }
}
