<?php

namespace App\Actions\Applications;

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Staff upload of a signed contract copy. The application row is locked and its persisted
 * state re-verified (application -> contract lock order) before the contract is touched, so
 * a stale replay cannot overwrite an already-signed contract or duplicate the transition.
 * Rejects a missing or already-signed-off contract; contract update, application state, and
 * activity are written in one transaction. If the database work fails, the uploaded file is
 * compensated (deleted).
 */
final class UploadSignedContractAction
{
    public function execute(Application $application, string $filePath, ?string $notes = null): Application
    {
        try {
            DB::transaction(function () use ($application, $filePath, $notes) {
                $locked = LockApplication::inState($application, AwaitingContractSignature::class);

                $contract = $locked->contract()->lockForUpdate()->first();

                if ($contract === null || $contract->isSignedOff()) {
                    throw new ApplicationIncompleteException(__('alerts.application.contract_missing'));
                }

                $contract->update([
                    'file_path' => $filePath,
                    'signed_at' => now(),
                    'signed_by_applicant' => false,
                ]);

                $locked->status->transitionTo(
                    AwaitingBranchReview::class,
                    $notes ?? __('alerts.application.application_contract_uploaded_by_staff'),
                );
            }, attempts: 3);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($filePath);

            throw $e;
        }

        return $application->fresh();
    }
}
