<?php

namespace App\States\Leads;

class Interested extends LeadState
{
    public static $name = 'interested';

    public function getLabel(): string
    {
        return __('admin.lead.states.interested');
    }

    public function color(): string
    {
        return 'success';
    }
}
