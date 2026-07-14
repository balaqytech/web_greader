<?php

namespace App\States\Applications\Transitions;

use App\States\Applications\AwaitingBranchReview;

class AwaitingBranchReviewToCancelled extends CancelApplicationTransition
{
    protected function fromStateName(): string
    {
        return AwaitingBranchReview::$name;
    }
}
