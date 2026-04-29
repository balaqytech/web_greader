<?php

namespace App\States\Applications;

class UnderReview extends ApplicationState
{
    public static string $name = 'under_review';

    public function getLabel(): string
    {
        return __('admin.application.states.under_review');
    }

    public function getColor(): string
    {
        return 'primary';
    }
}
