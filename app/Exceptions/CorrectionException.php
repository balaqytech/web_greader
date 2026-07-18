<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * A correction request or completion could not be applied. Every guard here runs under the
 * application's row lock so a stale replay cannot open a second correction, complete a
 * correction twice, or return an application in the wrong direction.
 */
class CorrectionException extends Exception
{
    private function __construct(
        string $message,
        public readonly string $translationKey,
    ) {
        parent::__construct($message);
    }

    public static function reasonRequired(): self
    {
        return new self('A correction reason is required.', 'alerts.application.correction_reason_required');
    }

    public static function checklistRequired(): self
    {
        return new self('At least one checklist item is required.', 'alerts.application.correction_checklist_required');
    }

    public static function alreadyOpen(): self
    {
        return new self('An open correction already exists for this application.', 'alerts.application.correction_already_open');
    }

    public static function noneOpen(): self
    {
        return new self('There is no open correction for this application.', 'alerts.application.correction_none_open');
    }

    public static function checklistIncomplete(): self
    {
        return new self('Every checklist item must be completed before closing.', 'alerts.application.correction_checklist_incomplete');
    }

    public static function notContractRelevant(): self
    {
        return new self('This correction is not contract-relevant.', 'alerts.application.correction_not_contract_relevant');
    }

    public static function isContractRelevant(): self
    {
        return new self('This correction is contract-relevant.', 'alerts.application.correction_is_contract_relevant');
    }
}
