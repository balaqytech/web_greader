<?php

namespace App\States\Leads;

use App\States\Leads\Transitions\ContactedLeadToInterested;
use App\States\Leads\Transitions\ContactedLeadToNoResponse;
use App\States\Leads\Transitions\ContactedLeadToNotInterested;
use App\States\Leads\Transitions\NewLeadToContactedLead;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class LeadState extends State
{
    abstract public static function getLabel(): string;

    abstract public static function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(NewLead::class)
            ->allowTransition(NewLead::class, ContactedLead::class, NewLeadToContactedLead::class)
            ->allowTransition([NewLead::class, ContactedLead::class], Interested::class, ContactedLeadToInterested::class)
            ->allowTransition([NewLead::class, ContactedLead::class], NotInterested::class, ContactedLeadToNotInterested::class)
            ->allowTransition([NewLead::class, ContactedLead::class], NoResponse::class, ContactedLeadToNoResponse::class);
    }
}
