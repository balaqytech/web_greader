<?php

namespace App\States\Applications;

class WaitingContract extends ApplicationState
{
    public static string $name = 'waiting_contract';

    public function getLabel(): string
    {
        return __('admin.application.states.waiting_contract');
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
