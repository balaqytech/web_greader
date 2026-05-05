<?php

namespace App\Actions\Applications;

use App\Exceptions\ContractTokenExpiredException;
use App\Models\ApplicationContract;

class SignApplicationContractAction
{
    public function handle(
        ApplicationContract $contract,
        string $signedByApplicant,
        ?string $signaturePath = null
    ): ApplicationContract {
        if ($contract->isTokenExpired()) {
            throw new ContractTokenExpiredException;
        }

        $contract->update([
            'signed_at' => now(),
            'signed_by_applicant' => $signedByApplicant,
            'signature_path' => $signaturePath,
        ]);

        return $contract;
    }
}
