<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\Models\Application;
use App\Models\Scopes\BranchScope;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\CorrectionRequested;
use Illuminate\Console\Command;

/**
 * Backfills the document requirement set onto applications that passed the registration-fee
 * gate before Phase 3 existed, so they never had {@see SyncRequiredDocumentsAction} run for
 * them. New applications get their requirements from the payment-gate transition; this command
 * is only for the pre-existing backlog.
 *
 * Eligibility is any application in a post-fee, non-terminal state — data completion through
 * acceptance. Pre-fee, cancelled, and rejected applications are skipped: they have no reason
 * to carry document requirements.
 *
 * Idempotent from top to bottom. The underlying action re-flags rather than recreates, so a
 * second run is a no-op, and a run interrupted midway can simply be run again. Processes in
 * chunks to stay flat in memory over a large backlog, and bypasses BranchScope explicitly
 * because it runs unauthenticated on the console and must cover every branch.
 */
class SyncDocumentRequirementsCommand extends Command
{
    protected $signature = 'applications:sync-document-requirements
                            {--application= : Restrict the backfill to a single application id}
                            {--chunk=200 : How many applications to load per chunk}';

    protected $description = 'Backfill the document requirement set onto existing post-fee applications.';

    /**
     * @var list<class-string>
     */
    private const ELIGIBLE_STATES = [
        AwaitingApplicationCompletion::class,
        AwaitingContractSignature::class,
        AwaitingBranchReview::class,
        CorrectionRequested::class,
        Accepted::class,
    ];

    public function handle(SyncRequiredDocumentsAction $sync): int
    {
        $query = Application::withoutGlobalScope(BranchScope::class)
            ->whereIn('status', array_map(static fn (string $state): string => $state::$name, self::ELIGIBLE_STATES));

        if ($this->option('application') !== null) {
            $query->whereKey((int) $this->option('application'));
        }

        $processed = 0;

        $query->chunkById((int) $this->option('chunk'), function ($applications) use ($sync, &$processed): void {
            foreach ($applications as $application) {
                $sync->execute($application);
                $processed++;
            }
        });

        $this->info("Synced document requirements for {$processed} application(s).");

        return self::SUCCESS;
    }
}
