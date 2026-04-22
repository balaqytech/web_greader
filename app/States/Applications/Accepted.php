<?php

namespace App\States\Applications;

class Accepted extends ApplicationState
{
    public static string $name = 'accepted';

    public static function getLabel(): string
    {
        return __('admin.application_status.accepted');
    }

    public static function color(): string
    {
        return 'success';
    }
}
