<?php

namespace App\States\Applications;

class WaitingContractSignature extends ApplicationState
{
    public static string $name = 'waiting_contract_signature';

    public function getLabel(): string
    {
        return __('admin.application.states.waiting_contract_signature');
    }

    public function getColor(): string
    {
        return 'info';
    }
}
