<?php

namespace App\States\Applications;

class Rejected extends ApplicationState
{
    public static string $name = 'rejected';

    public static function getLabel(): string
    {
        return __('admin.application_status.rejected');
    }

    public static function color(): string
    {
        return 'danger';
    }
}
