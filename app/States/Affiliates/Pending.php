<?php

namespace App\States\Affiliates;

class Pending extends AffiliateState
{
    public static string $name = 'pending';

    public static function getLabel(): string
    {
        return __('admin.affiliate.states.pending');
    }

    public static function color(): string
    {
        return 'info';
    }
}
