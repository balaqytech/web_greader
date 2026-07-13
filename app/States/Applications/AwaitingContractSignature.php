<?php

namespace App\States\Applications;

class AwaitingContractSignature extends ApplicationState
{
    public static string $name = 'awaiting_contract_signature';

    public function getLabel(): string
    {
        return __('admin.application.states.awaiting_contract_signature');
    }

    public function getColor(): string
    {
        return 'info';
    }
}
