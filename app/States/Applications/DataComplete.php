<?php

namespace App\States\Applications;

class DataComplete extends ApplicationState
{
    public static string $name = 'data_complete';

    public function getLabel(): string
    {
        return __('admin.application.states.data_complete');
    }

    public function getColor(): string
    {
        return 'info';
    }
}
