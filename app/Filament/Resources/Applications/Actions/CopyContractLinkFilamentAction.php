<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\AwaitingContractSignature;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CopyContractLinkFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'copy_contract_link';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.copy_contract_link'));
        $this->icon('heroicon-o-link');
        $this->color('gray');
        $this->outlined();

        $this->modalHeading(__('admin.application.contract_link'));
        $this->modalSubmitActionLabel(__('admin.application.actions.close'));
        $this->modalCancelActionLabel('');

        $this->schema(function (Schema $schema, Application $record): Schema {
            $link = route('contract.show', $record->contract->token);

            return $schema->components([
                TextEntry::make('contract_link')
                    ->label(__('admin.application.contract_link'))
                    ->state($link)
                    ->copyable()
                    ->copyMessage(__('admin.application.actions.link_copied'))
                    ->icon('heroicon-o-link')
                    ->columnSpanFull(),
                TextEntry::make('contract_token_expires_at')
                    ->label(__('admin.application.contract_link_expires_at'))
                    ->state($record->contract->token_expires_at)
                    ->dateTime()
                    ->columnSpanFull(),
            ]);
        });

        $this->action(fn () => null);

        $this->visible(fn (?Application $record): bool => $record?->status instanceof AwaitingContractSignature);
    }
}
