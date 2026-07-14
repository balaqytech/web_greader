<?php

namespace App\States\Applications\Transitions;

use App\States\Applications\AwaitingApplicationCompletion;

class AwaitingApplicationCompletionToCancelled extends CancelApplicationTransition
{
    protected function fromState(): string
    {
        return AwaitingApplicationCompletion::class;
    }
}
