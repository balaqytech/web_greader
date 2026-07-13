<?php

namespace App\States\Applications;

class CorrectionRequested extends ApplicationState
{
    public static string $name = 'correction_requested';

    public function getLabel(): string
    {
        return __('admin.application.states.correction_requested');
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
