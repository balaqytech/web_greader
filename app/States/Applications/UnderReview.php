<?php

namespace App\States\Applications;

class UnderReview extends ApplicationState
{
    public static string $name = 'under_review';

    public static function getLabel(): string
    {
        return __('admin.application_status.under_review');
    }

    public static function color(): string
    {
        return 'primary';
    }
}
