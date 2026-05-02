<?php

namespace App\States\Applications;

class Draft extends ApplicationState
{
    public static string $name = 'draft';

    public function getLabel(): string
    {
        return __('admin.application.states.draft');
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
