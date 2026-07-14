<?php

namespace App\States\Applications\Transitions;

use App\States\Applications\AwaitingRegistrationFee;

class AwaitingRegistrationFeeToCancelled extends CancelApplicationTransition
{
    protected function fromStateName(): string
    {
        return AwaitingRegistrationFee::$name;
    }
}
