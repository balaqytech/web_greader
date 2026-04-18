<?php

namespace App\States\Affiliates;

use App\States\Affiliates\Transitions\PendingToVerified;
use App\States\Affiliates\Transitions\ToRejected;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class AffiliateState extends State
{
    abstract public static function getLabel(): string;

    abstract public static function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Verified::class, PendingToVerified::class)
            ->allowTransition([Pending::class, Verified::class], Rejected::class, ToRejected::class);
    }
}
