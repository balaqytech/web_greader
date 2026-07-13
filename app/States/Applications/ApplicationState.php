<?php

namespace App\States\Applications;

use App\States\Applications\Transitions\AwaitingApplicationCompletionToAwaitingContractSignature;
use App\States\Applications\Transitions\AwaitingApplicationCompletionToCancelled;
use App\States\Applications\Transitions\AwaitingBranchReviewToAccepted;
use App\States\Applications\Transitions\AwaitingBranchReviewToCancelled;
use App\States\Applications\Transitions\AwaitingBranchReviewToRejected;
use App\States\Applications\Transitions\AwaitingContractSignatureToAwaitingApplicationCompletion;
use App\States\Applications\Transitions\AwaitingContractSignatureToAwaitingBranchReview;
use App\States\Applications\Transitions\AwaitingContractSignatureToCancelled;
use App\States\Applications\Transitions\AwaitingRegistrationFeeToCancelled;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApplicationState extends State implements HasColor, HasLabel
{
    /**
     * Phase 0 registers only the baseline transitions that are fully supported
     * end-to-end. Deferred edges (payment-gated AwaitingRegistrationFee ->
     * AwaitingApplicationCompletion, and the CorrectionRequested edges) are registered
     * in the phases that supply their dependencies (payments, corrections).
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(AwaitingRegistrationFee::class)
            ->allowTransition(AwaitingApplicationCompletion::class, AwaitingContractSignature::class, AwaitingApplicationCompletionToAwaitingContractSignature::class)
            ->allowTransition(AwaitingContractSignature::class, AwaitingApplicationCompletion::class, AwaitingContractSignatureToAwaitingApplicationCompletion::class)
            ->allowTransition(AwaitingContractSignature::class, AwaitingBranchReview::class, AwaitingContractSignatureToAwaitingBranchReview::class)
            ->allowTransition(AwaitingBranchReview::class, Accepted::class, AwaitingBranchReviewToAccepted::class)
            ->allowTransition(AwaitingBranchReview::class, Rejected::class, AwaitingBranchReviewToRejected::class)
            ->allowTransition(AwaitingRegistrationFee::class, Cancelled::class, AwaitingRegistrationFeeToCancelled::class)
            ->allowTransition(AwaitingApplicationCompletion::class, Cancelled::class, AwaitingApplicationCompletionToCancelled::class)
            ->allowTransition(AwaitingContractSignature::class, Cancelled::class, AwaitingContractSignatureToCancelled::class)
            ->allowTransition(AwaitingBranchReview::class, Cancelled::class, AwaitingBranchReviewToCancelled::class);
    }
}
