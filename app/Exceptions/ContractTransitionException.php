<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\ApplicationContract;
use Exception;

/**
 * A contract-version state change could not be applied. Signing requires a persisted signed
 * artifact; every guard here runs under the contract's row lock so a decision cannot be applied
 * against a version that has since been superseded or cancelled.
 */
class ContractTransitionException extends Exception
{
    private function __construct(
        string $message,
        public readonly string $translationKey,
    ) {
        parent::__construct($message);
    }

    public static function signedArtifactRequired(ApplicationContract $contract): self
    {
        return new self(
            sprintf(
                'Contract %d cannot be marked signed without a persisted signed artifact.',
                $contract->getKey(),
            ),
            'alerts.application.contract_not_signed',
        );
    }
}
