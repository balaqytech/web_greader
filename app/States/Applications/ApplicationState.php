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
use App\States\Applications\Transitions\AwaitingRegistrationFeeToAwaitingApplicationCompletion;
use App\States\Applications\Transitions\AwaitingRegistrationFeeToCancelled;
use App\States\Applications\Transitions\CorrectionRequestedToCancelled;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApplicationState extends State implements HasColor, HasLabel
{
    /**
     * Only transitions that are fully supported end-to-end are registered. The remaining
     * deferred edges (the CorrectionRequested ones) are registered by the phases that supply
     * their dependencies.
     *
     * The fee gate (AwaitingRegistrationFee -> AwaitingApplicationCompletion) is registered
     * here as of the payments phase. Its transition takes a **required** paid Payment, so
     * there is no way to express "advance past the fee gate, trust me" — a caller without
     * one fails before any state is written.
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(AwaitingRegistrationFee::class)
            ->allowTransition(AwaitingRegistrationFee::class, AwaitingApplicationCompletion::class, AwaitingRegistrationFeeToAwaitingApplicationCompletion::class)
            ->allowTransition(AwaitingApplicationCompletion::class, AwaitingContractSignature::class, AwaitingApplicationCompletionToAwaitingContractSignature::class)
            ->allowTransition(AwaitingContractSignature::class, AwaitingApplicationCompletion::class, AwaitingContractSignatureToAwaitingApplicationCompletion::class)
            ->allowTransition(AwaitingContractSignature::class, AwaitingBranchReview::class, AwaitingContractSignatureToAwaitingBranchReview::class)
            ->allowTransition(AwaitingBranchReview::class, Accepted::class, AwaitingBranchReviewToAccepted::class)
            ->allowTransition(AwaitingBranchReview::class, Rejected::class, AwaitingBranchReviewToRejected::class)
            ->allowTransition(AwaitingRegistrationFee::class, Cancelled::class, AwaitingRegistrationFeeToCancelled::class)
            ->allowTransition(AwaitingApplicationCompletion::class, Cancelled::class, AwaitingApplicationCompletionToCancelled::class)
            ->allowTransition(AwaitingContractSignature::class, Cancelled::class, AwaitingContractSignatureToCancelled::class)
            ->allowTransition(AwaitingBranchReview::class, Cancelled::class, AwaitingBranchReviewToCancelled::class)
            ->allowTransition(CorrectionRequested::class, Cancelled::class, CorrectionRequestedToCancelled::class);
    }
}
