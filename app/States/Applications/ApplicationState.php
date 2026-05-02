<?php

namespace App\States\Applications;

use App\States\Applications\Transitions\DraftToCancelled;
use App\States\Applications\Transitions\DraftToSubmitted;
use App\States\Applications\Transitions\SubmittedToCancelled;
use App\States\Applications\Transitions\SubmittedToWaitingContractSignature;
use App\States\Applications\Transitions\UnderReviewToAccepted;
use App\States\Applications\Transitions\UnderReviewToRejected;
use App\States\Applications\Transitions\WaitingContractSignatureToCancelled;
use App\States\Applications\Transitions\WaitingContractSignatureToSubmitted;
use App\States\Applications\Transitions\WaitingContractSignatureToUnderReview;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApplicationState extends State implements HasColor, HasLabel
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Submitted::class, DraftToSubmitted::class)
            ->allowTransition(Submitted::class, WaitingContractSignature::class, SubmittedToWaitingContractSignature::class)
            ->allowTransition(WaitingContractSignature::class, Submitted::class, WaitingContractSignatureToSubmitted::class)
            ->allowTransition(WaitingContractSignature::class, UnderReview::class, WaitingContractSignatureToUnderReview::class)
            ->allowTransition(UnderReview::class, Accepted::class, UnderReviewToAccepted::class)
            ->allowTransition(UnderReview::class, Rejected::class, UnderReviewToRejected::class)
            ->allowTransition(Draft::class, Cancelled::class, DraftToCancelled::class)
            ->allowTransition(Submitted::class, Cancelled::class, SubmittedToCancelled::class)
            ->allowTransition(WaitingContractSignature::class, Cancelled::class, WaitingContractSignatureToCancelled::class);
    }
}
