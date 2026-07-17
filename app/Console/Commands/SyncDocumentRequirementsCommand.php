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
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Backfills the document requirement set onto applications that passed the registration-fee
 * gate before Phase 3 existed, so they never had {@see SyncRequiredDocumentsAction} run for
 * them. New applications get their requirements from the payment-gate transition; this command
 * is only for the pre-existing backlog.
 *
 * Eligibility includes active post-fee applications, rejected records, and records cancelled
 * after reaching data completion because their document history remains operationally relevant.
 * Pre-fee cancellations are skipped.
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
        Rejected::class,
    ];

    public function handle(SyncRequiredDocumentsAction $sync): int
    {
        $postFeeActivityStates = [
            ...array_map(static fn (string $state): string => $state::$name, self::ELIGIBLE_STATES),
            'submitted',
            'waiting_contract_signature',
            'under_review',
        ];

        $query = Application::withoutGlobalScope(BranchScope::class)
            ->where(function (Builder $query) use ($postFeeActivityStates): void {
                $query->whereIn('status', array_map(static fn (string $state): string => $state::$name, self::ELIGIBLE_STATES))
                    ->orWhere(function (Builder $query) use ($postFeeActivityStates): void {
                        $query->where('status', Cancelled::$name)
                            ->whereHas('activities', function (Builder $query) use ($postFeeActivityStates): void {
                                $query->whereIn('from_state', $postFeeActivityStates)
                                    ->orWhereIn('to_state', $postFeeActivityStates);
                            });
                    });
            });

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
