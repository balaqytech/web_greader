<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\Submitted;
use App\States\Applications\WaitingContractSignature;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

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
                ->rows(3)
                ->maxLength(255),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof Submitted
                && ($record->status->canTransitionTo(WaitingContractSignature::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            try {
                $record->status->transitionTo(WaitingContractSignature::class, $data['notes']);

                Notification::make()
                    ->title(__('admin.application.actions.move_to_waiting_contract_success'))
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
