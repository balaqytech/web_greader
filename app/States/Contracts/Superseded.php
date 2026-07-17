<?php

declare(strict_types=1);

namespace App\States\Contracts;

class Superseded extends ContractState
{
    public static string $name = 'superseded';

    public function getLabel(): string
    {
        return __('admin.application.contract_states.superseded');
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
