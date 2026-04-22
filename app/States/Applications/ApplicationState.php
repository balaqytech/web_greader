<?php

namespace App\States\Applications;

use App\States\Applications\Transitions\DataCompleteToUnderReview;
use App\States\Applications\Transitions\PendingToDataComplete;
use App\States\Applications\Transitions\UnderReviewToAccepted;
use App\States\Applications\Transitions\UnderReviewToPending;
use App\States\Applications\Transitions\UnderReviewToRejected;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApplicationState extends State
{
    abstract public static function getLabel(): string;

    abstract public static function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingRegistration::class)
            ->allowTransition(PendingRegistration::class, DataComplete::class, PendingToDataComplete::class)
            ->allowTransition(DataComplete::class, UnderReview::class, DataCompleteToUnderReview::class)
            ->allowTransition(UnderReview::class, Accepted::class, UnderReviewToAccepted::class)
            ->allowTransition(UnderReview::class, Rejected::class, UnderReviewToRejected::class)
            ->allowTransition(UnderReview::class, PendingRegistration::class, UnderReviewToPending::class);
    }
}
