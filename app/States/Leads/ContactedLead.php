<?php

namespace App\States\Leads;

class ContactedLead extends LeadState
{
    public static $name = 'contacted';

    public static function getLabel(): string
    {
        return __('admin.lead.states.contacted');
    }

    public static function color(): string
    {
        return 'info';
    }
}
