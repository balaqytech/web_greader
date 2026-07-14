<?php

namespace App\Actions\Applications;

use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Staff upload of a signed contract copy. Rejects a missing contract, persists the signed
 * artifact onto the existing contract, then runs the guarded transition into branch review
 * — contract update, application state, and activity in one transaction. If the database
 * work fails, the uploaded file is compensated (deleted).
 */
final class UploadSignedContractAction
{
    public function execute(Application $application, string $filePath, ?string $notes = null): Application
    {
        $application->loadMissing('contract');

        if ($application->contract === null) {
            throw new ApplicationIncompleteException(__('alerts.application.contract_missing'));
        }

        try {
            DB::transaction(function () use ($application, $filePath, $notes) {
                $application->contract->update([
                    'file_path' => $filePath,
                    'signed_at' => now(),
                    'signed_by_applicant' => false,
                ]);

                $application->status->transitionTo(
                    AwaitingBranchReview::class,
                    $notes ?? __('alerts.application.application_contract_uploaded_by_staff'),
                );
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($filePath);

            throw $e;
        }

        return $application->fresh();
    }
}
