<?php

namespace App\States\Leads;

class NotInterested extends LeadState
{
    public static $name = 'not_interested';

    public function getLabel(): string
    {
        return __('admin.lead.states.not_interested');
    }

    public function color(): string
    {
        return 'danger';
    }

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
}
