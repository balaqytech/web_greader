<?php

namespace App\States\Applications;

class Accepted extends ApplicationState
{
    public static string $name = 'accepted';

    public function getLabel(): string
    {
        return __('admin.application.states.accepted');
    }

    public function getColor(): string
    {
        return 'success';
    }
}
