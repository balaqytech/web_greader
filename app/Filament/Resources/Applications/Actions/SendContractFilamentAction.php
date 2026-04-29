<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\SendContractAction;
use App\Models\Application;
use App\States\Applications\WaitingContract;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class SendContractFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send_contract';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.send_contract'));
        $this->icon('heroicon-o-paper-airplane');
        $this->color('primary');
        $this->requiresConfirmation();

        $this->modalHeading(__('admin.application.actions.send_contract'));
        $this->modalDescription(__('admin.application.send_contract_description'));

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof WaitingContract
        );

        $this->action(function (Application $record) {
            try {
                $application = app(SendContractAction::class)->execute($record, Auth::id());

                $link = route('contract.show', $application->contract_token);

                Notification::make()
                    ->title(__('admin.application.actions.send_contract_success'))
                    ->body(__('admin.application.contract_link').': '.$link)
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title(__('admin.application.actions.send_contract_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
