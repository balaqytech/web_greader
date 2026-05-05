<?php

namespace App\States\Applications;

class Submitted extends ApplicationState
{
    public static string $name = 'submitted';

    public function getLabel(): string
    {
        return __('admin.application.states.submitted');
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
