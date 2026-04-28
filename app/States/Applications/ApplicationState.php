<?php

namespace App\States\Applications;

use App\States\Applications\Transitions\DataCompleteToWaitingContract;
use App\States\Applications\Transitions\PendingRegistrationToDataComplete;
use App\States\Applications\Transitions\UnderReviewToAccepted;
use App\States\Applications\Transitions\UnderReviewToPending;
use App\States\Applications\Transitions\UnderReviewToRejected;
use App\States\Applications\Transitions\WaitingContractToDataComplete;
use App\States\Applications\Transitions\WaitingContractToUnderReview;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApplicationState extends State implements HasColor, HasLabel
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingRegistration::class)
            ->allowTransition(PendingRegistration::class, DataComplete::class, PendingRegistrationToDataComplete::class)
            ->allowTransition(DataComplete::class, WaitingContract::class, DataCompleteToWaitingContract::class)
            ->allowTransition(WaitingContract::class, UnderReview::class, WaitingContractToUnderReview::class)
            ->allowTransition(WaitingContract::class, DataComplete::class, WaitingContractToDataComplete::class)
            ->allowTransition(UnderReview::class, Accepted::class, UnderReviewToAccepted::class)
            ->allowTransition(UnderReview::class, Rejected::class, UnderReviewToRejected::class)
            ->allowTransition(UnderReview::class, PendingRegistration::class, UnderReviewToPending::class);
    }
}
