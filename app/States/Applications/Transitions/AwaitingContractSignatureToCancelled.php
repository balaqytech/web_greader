<?php

namespace App\States\Applications\Transitions;

use App\States\Applications\AwaitingContractSignature;

class AwaitingContractSignatureToCancelled extends CancelApplicationTransition
{
    protected function fromState(): string
    {
        return AwaitingContractSignature::class;
    }
}
