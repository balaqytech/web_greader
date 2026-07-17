<?php

declare(strict_types=1);

namespace App\States\Contracts;

class Signed extends ContractState
{
    public static string $name = 'signed';

    public function getLabel(): string
    {
        return __('admin.application.contract_states.signed');
    }

    public function getColor(): string
    {
        return 'success';
    }
}
