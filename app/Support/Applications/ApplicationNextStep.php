<?php

declare(strict_types=1);

namespace App\Support\Applications;

use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;

/**
 * Deterministic map from an application's current state to the single next action a guardian
 * (or the chatbot on their behalf) should take. Every state resolves to exactly one code; a
 * terminal or dead-end state resolves to `none`. This is the only place the mapping lives, so
 * the status API and any future consumer can never disagree on what "next" means.
 */
final class ApplicationNextStep
{
    public const PayRegistrationFee = 'pay_registration_fee';

    public const CompleteApplicationData = 'complete_application_data';

    public const SignContract = 'sign_contract';

    public const AwaitBranchReview = 'await_branch_review';

    public const CompleteCorrections = 'complete_corrections';

    public const Completed = 'completed';

    public const None = 'none';

    public static function code(Application $application): string
    {
        return match (true) {
            $application->status instanceof AwaitingRegistrationFee => self::PayRegistrationFee,
            $application->status instanceof AwaitingApplicationCompletion => self::CompleteApplicationData,
            $application->status instanceof AwaitingContractSignature => self::SignContract,
            $application->status instanceof AwaitingBranchReview => self::AwaitBranchReview,
            $application->status instanceof CorrectionRequested => self::CompleteCorrections,
            $application->status instanceof Accepted => self::Completed,
            $application->status instanceof Rejected, $application->status instanceof Cancelled => self::None,
            default => self::None,
        };
    }

    public static function label(string $code): string
    {
        return __('alerts.api.next_steps.'.$code);
    }
}
