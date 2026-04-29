<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\SendContractAction;
use App\Models\Application;
use App\States\Applications\DataComplete;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class MoveToWaitingContractFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'move_to_waiting_contract';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.move_to_waiting_contract'));
        $this->icon('heroicon-o-paper-airplane');
        $this->color('primary');
        $this->requiresConfirmation();

        $this->modalHeading(__('admin.application.actions.move_to_waiting_contract'));
        $this->modalDescription(__('admin.application.move_to_waiting_contract_description'));
        $this->modalSubmitActionLabel(__('admin.application.actions.move_to_waiting_contract'));

        $this->schema([
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof DataComplete
        );

        $this->action(function (Application $record, array $data) {
            try {
                $application = app(SendContractAction::class)->execute(
                    $record,
                    Auth::id(),
                    $data['notes'] ?? null,
                );

                $link = route('contract.show', $application->contract_token);

                Notification::make()
                    ->title(__('admin.application.actions.move_to_waiting_contract_success'))
                    ->body(__('admin.application.contract_link').': '.$link)
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
