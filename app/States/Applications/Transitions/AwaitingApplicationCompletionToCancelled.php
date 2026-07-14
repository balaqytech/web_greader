<?php

namespace App\States\Applications\Transitions;

use App\States\Applications\AwaitingApplicationCompletion;

class AwaitingApplicationCompletionToCancelled extends CancelApplicationTransition
{
    protected function fromStateName(): string
    {
        return AwaitingApplicationCompletion::$name;
    }
}
