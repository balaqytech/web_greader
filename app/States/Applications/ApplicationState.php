<?php

namespace App\States\Applications;

use App\States\Applications\Transitions\AwaitingApplicationCompletionToAwaitingContractSignature;
use App\States\Applications\Transitions\AwaitingApplicationCompletionToCancelled;
use App\States\Applications\Transitions\AwaitingBranchReviewToAccepted;
use App\States\Applications\Transitions\AwaitingBranchReviewToCancelled;
use App\States\Applications\Transitions\AwaitingBranchReviewToCorrectionRequested;
use App\States\Applications\Transitions\AwaitingBranchReviewToRejected;
use App\States\Applications\Transitions\AwaitingContractSignatureToAwaitingApplicationCompletion;
use App\States\Applications\Transitions\AwaitingContractSignatureToAwaitingBranchReview;
use App\States\Applications\Transitions\AwaitingContractSignatureToCancelled;
use App\States\Applications\Transitions\AwaitingRegistrationFeeToAwaitingApplicationCompletion;
use App\States\Applications\Transitions\AwaitingRegistrationFeeToCancelled;
use App\States\Applications\Transitions\CorrectionRequestedToAwaitingBranchReview;
use App\States\Applications\Transitions\CorrectionRequestedToAwaitingContractSignature;
use App\States\Applications\Transitions\CorrectionRequestedToCancelled;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApplicationState extends State implements HasColor, HasLabel
{
    /**
     * Every §3.2 edge is now registered end-to-end. The fee gate
     * (AwaitingRegistrationFee -> AwaitingApplicationCompletion) takes a **required** paid
     * Payment, so there is no way to express "advance past the fee gate, trust me". The
     * correction edges (AwaitingBranchReview -> CorrectionRequested and both exits of
     * CorrectionRequested) are driven by the correction workflow: the two exits classify the
     * correction under lock, so the non-contract-relevant return and the contract-relevant
     * re-signature edges are mutually exclusive and each refuses the other's case.
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
            ->allowTransition(AwaitingBranchReview::class, CorrectionRequested::class, AwaitingBranchReviewToCorrectionRequested::class)
            ->allowTransition(CorrectionRequested::class, AwaitingBranchReview::class, CorrectionRequestedToAwaitingBranchReview::class)
            ->allowTransition(CorrectionRequested::class, AwaitingContractSignature::class, CorrectionRequestedToAwaitingContractSignature::class)
            ->allowTransition(AwaitingRegistrationFee::class, Cancelled::class, AwaitingRegistrationFeeToCancelled::class)
            ->allowTransition(AwaitingApplicationCompletion::class, Cancelled::class, AwaitingApplicationCompletionToCancelled::class)
            ->allowTransition(AwaitingContractSignature::class, Cancelled::class, AwaitingContractSignatureToCancelled::class)
            ->allowTransition(AwaitingBranchReview::class, Cancelled::class, AwaitingBranchReviewToCancelled::class)
            ->allowTransition(CorrectionRequested::class, Cancelled::class, CorrectionRequestedToCancelled::class);
    }
}
