<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SignContractOnlineAction
{
    public function execute(Application $application, string $base64Signature): Application
    {
        if (! $application->status instanceof WaitingContract) {
            throw new InvalidArgumentException('Application is not waiting for contract.');
        }

        if (! $application->hasValidContractToken()) {
            throw new InvalidArgumentException('Contract token is invalid or expired.');
        }

        // Decode base64 signature
        $imageParts = explode(';base64,', $base64Signature);
        if (count($imageParts) !== 2) {
            throw new InvalidArgumentException('Invalid signature data.');
        }

        $imageTypeAux = explode('image/', $imageParts[0]);
        if (count($imageTypeAux) !== 2) {
            throw new InvalidArgumentException('Invalid signature format.');
        }

        $imageType = $imageTypeAux[1];
        $imageBase64 = base64_decode($imageParts[1]);

        $filename = 'contract_signature_' . Str::random(10) . '.' . $imageType;
        $path = 'contracts/signatures/' . $filename;

        Storage::disk('public')->put($path, $imageBase64);

        $application->status->transitionTo(
            UnderReview::class,
            signedByApplicant: true,
            signaturePath: $path,
            notes: 'Contract signed online by applicant.'
        );

        return $application->fresh();
    }
}
