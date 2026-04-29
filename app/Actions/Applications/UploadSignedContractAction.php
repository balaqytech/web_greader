<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContract;
use InvalidArgumentException;

final class UploadSignedContractAction
{
    public function execute(Application $application, string $filePath, ?int $transitionedBy = null): Application
    {
        if (! $application->status instanceof WaitingContract) {
            throw new InvalidArgumentException('Application is not waiting for contract.');
        }

        $application->status->transitionTo(
            UnderReview::class,
            signedByApplicant: false,
            filePath: $filePath,
            transitionedBy: $transitionedBy,
            notes: 'Contract uploaded by staff.'
        );

        return $application->fresh();
    }
}
