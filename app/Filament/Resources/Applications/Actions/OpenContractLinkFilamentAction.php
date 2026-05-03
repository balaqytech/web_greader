<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\WaitingContractSignature;
use Filament\Actions\Action;

class OpenContractLinkFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'open_contract_link';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.open_contract_link'));
        $this->icon('heroicon-o-arrow-top-right-on-square');
        $this->color('gray');
        $this->outlined();

        $this->url(function (Application $record): string {
            return route('contract.show', $record->contract->token);
        });

        $this->disabled();

        $this->openUrlInNewTab();

        $this->visible(fn(?Application $record): bool => $record?->status instanceof WaitingContractSignature);
    }
}
