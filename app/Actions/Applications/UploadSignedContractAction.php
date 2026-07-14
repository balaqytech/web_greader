<?php

namespace App\Actions\Applications;

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\Models\ApplicationContract;
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
 * Rejects a missing or already-signed-off contract, and a candidate path that does not
 * actually exist on disk; contract update, application state, and activity are written in one
 * transaction. If the database work fails, the uploaded file is compensated (deleted) — but
 * only when no persisted contract still references that exact path, so a stale replay that
 * happens to reuse the winning request's path cannot delete the winner's artifact.
 */
final class UploadSignedContractAction
{
    public function execute(Application $application, string $filePath, ?string $notes = null): Application
    {
        if (! Storage::disk('public')->exists($filePath)) {
            throw new ApplicationIncompleteException(__('alerts.application.uploaded_file_missing'));
        }

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
            $this->deleteIfUnreferenced($filePath);

            throw $e;
        }

        return $application->fresh();
    }

    private function deleteIfUnreferenced(string $filePath): void
    {
        if (ApplicationContract::where('file_path', $filePath)->exists()) {
            return;
        }

        Storage::disk('public')->delete($filePath);
    }
}
