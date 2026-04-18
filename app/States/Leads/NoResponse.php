<?php

namespace App\States\Leads;

class NoResponse extends LeadState
{
    public static $name = 'no_response';

    public static function getLabel(): string
    {
        return __('admin.lead.states.no_response');
    }

    public static function color(): string
    {
        return 'warning';
    }

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
}
