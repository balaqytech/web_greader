<?php

namespace App\States\Leads;

class ContactedLead extends LeadState
{
    public static $name = 'contacted';

    public function getLabel(): string
    {
        return __('admin.lead.states.contacted');
    }

    public function color(): string
    {
        return 'info';
    }
}
