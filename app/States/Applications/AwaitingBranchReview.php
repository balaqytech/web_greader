<?php

namespace App\States\Applications;

class AwaitingBranchReview extends ApplicationState
{
    public static string $name = 'awaiting_branch_review';

    public function getLabel(): string
    {
        return __('admin.application.states.awaiting_branch_review');
    }

    public function getColor(): string
    {
        return 'primary';
    }
}
