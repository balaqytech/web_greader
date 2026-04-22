<?php

namespace App\States\Applications;

class DataComplete extends ApplicationState
{
    public static string $name = 'data_complete';

    public static function getLabel(): string
    {
        return __('admin.application_status.data_complete');
    }

    public static function color(): string
    {
        return 'info';
    }
}
