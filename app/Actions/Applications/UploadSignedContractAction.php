<?php

namespace App\Actions\Applications;

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Contracts\Signed;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Staff upload of a signed contract copy. The application row is locked and its persisted
 * state re-verified (application -> contract lock order) before the contract is touched, so
 * a stale replay cannot overwrite an already-signed contract or duplicate the transition.
 * Rejects a missing or already-signed-off contract, and a candidate path that does not
 * actually exist on disk — checked once up front and re-checked under lock, immediately
 * before persisting, since a separate process could remove the candidate in between; contract
 * update, application state, and activity are written in one transaction.
 *
 * This action never created the candidate file (the upload component did) and therefore
 * cannot prove exclusive ownership of it. On any failure — stale state, a missing contract,
 * or anything else — the candidate is deliberately left on disk rather than deleted: a
 * query-then-delete check cannot rule out a concurrent transaction persisting a reference to
 * that same path a moment later, and deleting another transaction's winning artifact would be
 * far worse than leaving an orphan behind. Reclaiming genuinely orphaned uploads is the job of
 * a separate, age-threshold cleanup process, not this action.
 */
final class UploadSignedContractAction
{
    public function execute(Application $application, string $filePath, ?string $notes = null): Application
    {
        if (! Storage::disk('public')->exists($filePath)) {
            throw new ApplicationIncompleteException(__('alerts.application.uploaded_file_missing'));
        }

        DB::transaction(function () use ($application, $filePath, $notes) {
            $locked = LockApplication::inState($application, AwaitingContractSignature::class);

            $contract = $locked->activeContract()->lockForUpdate()->first();

            if ($contract === null || $contract->isSignedOff()) {
                throw new ApplicationIncompleteException(__('alerts.application.contract_missing'));
            }

            if (! Storage::disk('public')->exists($filePath)) {
                throw new ApplicationIncompleteException(__('alerts.application.uploaded_file_missing'));
            }

            $contract->update([
                'file_path' => $filePath,
                'signed_at' => now(),
                'signed_by_applicant' => false,
            ]);

            $contract->status->transitionTo(Signed::class);

            $locked->status->transitionTo(
                AwaitingBranchReview::class,
                $notes ?? __('alerts.application.application_contract_uploaded_by_staff'),
            );
        }, attempts: 3);

        return $application->fresh();
    }
}
