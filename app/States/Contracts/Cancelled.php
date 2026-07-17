<?php

declare(strict_types=1);

namespace App\States\Contracts;

class Cancelled extends ContractState
{
    public static string $name = 'cancelled';

    public function getLabel(): string
    {
        return __('admin.application.contract_states.cancelled');
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
