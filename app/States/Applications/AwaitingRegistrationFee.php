<?php

namespace App\States\Applications;

class AwaitingRegistrationFee extends ApplicationState
{
    public static string $name = 'awaiting_registration_fee';

    public function getLabel(): string
    {
        return __('admin.application.states.awaiting_registration_fee');
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
