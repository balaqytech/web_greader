<?php

namespace App\States\Leads;

class NewLead extends LeadState
{
    public static $name = 'new';

    public function getLabel(): string
    {
        return __('admin.lead.states.new');
    }

    public function color(): string
    {
        return 'gray';
    }
}
