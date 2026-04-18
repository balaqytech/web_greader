<?php

namespace App\States\Leads;

class NewLead extends LeadState
{
    public static $name = 'new';

    public static function getLabel(): string
    {
        return __('admin.lead.states.new');
    }

    public static function color(): string
    {
        return 'gray';
    }
}
