<?php

namespace App\States\Applications;

class PendingRegistration extends ApplicationState
{
    public static string $name = 'pending_registration';

    public static function getLabel(): string
    {
        return __('admin.application_status.pending_registration');
    }

    public static function color(): string
    {
        return 'warning';
    }
}
