<?php

namespace App\States\Applications;

class AwaitingApplicationCompletion extends ApplicationState
{
    public static string $name = 'awaiting_application_completion';

    public function getLabel(): string
    {
        return __('admin.application.states.awaiting_application_completion');
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
