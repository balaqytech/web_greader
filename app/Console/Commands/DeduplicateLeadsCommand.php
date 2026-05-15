<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\Scopes\BranchScope;
use App\Services\LeadDuplicateResolver;
use App\Support\LeadIdentityNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('leads:deduplicate {--dry-run : Report merges without writing}')]
#[Description('Backfill lead identity fields and merge historical duplicates')]
class DeduplicateLeadsCommand extends Command
{
    public function handle(
        LeadIdentityNormalizer $normalizer,
        LeadDuplicateResolver $resolver,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Backfilling identity fields…');

        Lead::query()
            ->withoutGlobalScope(BranchScope::class)
            ->orderBy('id')
            ->each(function (Lead $lead) use ($normalizer, $dryRun): void {
                $lead->student_name_normalized = $normalizer->normalizeName($lead->student_name);
                $lead->identity_fingerprint = $normalizer->fingerprint(
                    $lead->whatsapp,
                    $lead->program_id,
                    $lead->season_id,
                    $lead->branch_id,
                    $lead->student_name,
                );

                if (! $dryRun && $lead->isDirty(['student_name_normalized', 'identity_fingerprint'])) {
                    $lead->saveQuietly();
                }
            });

        $this->info('Scanning for duplicate groups…');

        $scopeKeys = Lead::query()
            ->withoutGlobalScope(BranchScope::class)
            ->select('whatsapp', 'program_id', 'season_id', 'branch_id')
            ->distinct()
            ->get();

        $mergeCount = 0;

        foreach ($scopeKeys as $scope) {
            /** @var Collection<int, Lead> $leads */
            $leads = Lead::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('whatsapp', $scope->whatsapp)
                ->where('program_id', $scope->program_id)
                ->where('season_id', $scope->season_id)
                ->when(
                    $scope->branch_id === null,
                    fn ($query) => $query->whereNull('branch_id'),
                    fn ($query) => $query->where('branch_id', $scope->branch_id),
                )
                ->orderBy('id')
                ->get();

            if ($leads->count() < 2) {
                continue;
            }

            $canonicalById = [];

            foreach ($leads as $lead) {
                $match = $resolver->findExisting(
                    whatsapp: $lead->whatsapp,
                    studentName: $lead->student_name,
                    programId: $lead->program_id,
                    seasonId: $lead->season_id,
                    branchId: $lead->branch_id,
                );

                if ($match === null || $match->id === $lead->id) {
                    $canonicalById[$lead->id] = $lead;

                    continue;
                }

                $canonical = $canonicalById[$match->id] ?? $match;
                $canonical = $this->pickCanonical($canonical, $lead, $normalizer);

                $this->line(sprintf(
                    '  Merge lead #%d → #%d (%s)',
                    $lead->id,
                    $canonical->id,
                    $lead->student_name,
                ));

                if (! $dryRun) {
                    $this->mergeLeads($canonical, $lead, $normalizer);
                }

                $canonicalById[$canonical->id] = $canonical;
                $mergeCount++;
            }
        }

        $this->info($dryRun
            ? "Dry run complete. {$mergeCount} merge(s) would be performed."
            : "Done. {$mergeCount} duplicate lead(s) merged.");

        return self::SUCCESS;
    }

    private function pickCanonical(Lead $a, Lead $b, LeadIdentityNormalizer $normalizer): Lead
    {
        $aScore = $this->canonicalScore($a);
        $bScore = $this->canonicalScore($b);

        if ($bScore !== $aScore) {
            return $bScore > $aScore ? $b->fresh() : $a->fresh();
        }

        $longerName = $normalizer->preferLongerDisplayName($a->student_name, $b->student_name);

        return $longerName === $b->student_name ? $b->fresh() : $a->fresh();
    }

    private function canonicalScore(Lead $lead): int
    {
        $score = 0;

        if ($lead->application()->exists()) {
            $score += 100;
        }

        $score += $lead->contacts()->count() * 10;
        $score += count(app(LeadIdentityNormalizer::class)->tokenize($lead->student_name));

        return $score;
    }

    private function mergeLeads(Lead $canonical, Lead $duplicate, LeadIdentityNormalizer $normalizer): void
    {
        DB::transaction(function () use ($canonical, $duplicate, $normalizer): void {
            LeadContact::query()
                ->where('lead_id', $duplicate->id)
                ->update(['lead_id' => $canonical->id]);

            Application::query()
                ->where('lead_id', $duplicate->id)
                ->update(['lead_id' => $canonical->id]);

            $canonical->student_name = $normalizer->preferLongerDisplayName(
                $canonical->student_name,
                $duplicate->student_name,
            );
            $canonical->save();

            $duplicate->delete();
        });
    }
}
