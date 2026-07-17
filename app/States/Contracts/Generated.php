<?php

declare(strict_types=1);

namespace App\States\Contracts;

class Generated extends ContractState
{
    public static string $name = 'generated';

    public function getLabel(): string
    {
        return __('admin.application.contract_states.generated');
    }

    public function getColor(): string
    {
        return 'info';
    }
}
