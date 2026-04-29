<?php

namespace App\States\Applications;

class PendingRegistration extends ApplicationState
{
    public static string $name = 'pending_registration';

    public function getLabel(): string
    {
        return __('admin.application.states.pending_registration');
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
