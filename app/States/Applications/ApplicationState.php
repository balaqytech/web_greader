<?php

namespace App\States\Applications;

use App\States\Applications\Transitions\DataCompleteToUnderReview;
use App\States\Applications\Transitions\PendingRegistrationToUnderReview;
use App\States\Applications\Transitions\UnderReviewToAccepted;
use App\States\Applications\Transitions\UnderReviewToPending;
use App\States\Applications\Transitions\UnderReviewToRejected;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApplicationState extends State implements HasLabel, HasColor
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingRegistration::class)
            ->allowTransition(PendingRegistration::class, UnderReview::class, PendingRegistrationToUnderReview::class)
            ->allowTransition(UnderReview::class, Accepted::class, UnderReviewToAccepted::class)
            ->allowTransition(UnderReview::class, Rejected::class, UnderReviewToRejected::class)
            ->allowTransition(UnderReview::class, PendingRegistration::class, UnderReviewToPending::class);
    }
}
