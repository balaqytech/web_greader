<?php

namespace App\States\Leads;

class Interested extends LeadState
{
    public static $name = 'interested';

    public static function getLabel(): string
    {
        return __('admin.lead.states.interested');
    }

    public static function color(): string
    {
        return 'success';
    }
}
