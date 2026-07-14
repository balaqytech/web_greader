<?php

namespace App\States\Applications\Transitions;

use App\States\Applications\AwaitingContractSignature;

class AwaitingContractSignatureToCancelled extends CancelApplicationTransition
{
    protected function fromStateName(): string
    {
        return AwaitingContractSignature::$name;
    }
}
