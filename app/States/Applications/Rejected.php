<?php

namespace App\States\Applications;

class Rejected extends ApplicationState
{
    public static string $name = 'rejected';

    public function getLabel(): string
    {
        return __('admin.application.states.rejected');
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
