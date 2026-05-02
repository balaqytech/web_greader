<?php

namespace App\States\Applications;

class Cancelled extends ApplicationState
{
    public static string $name = 'cancelled';

    public function getLabel(): string
    {
        return __('admin.application.states.cancelled');
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
