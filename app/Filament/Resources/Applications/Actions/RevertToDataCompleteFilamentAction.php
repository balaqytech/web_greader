<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\DataComplete;
use App\States\Applications\WaitingContract;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RevertToDataCompleteFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revert_to_data_complete';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.revert_to_data_complete'));
        $this->icon('heroicon-o-arrow-uturn-left');
        $this->color('danger');
        $this->requiresConfirmation();
        $this->modalHeading(__('admin.application.actions.revert_to_data_complete'));

        $this->form([
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof WaitingContract
        );

        $this->action(function (Application $record, array $data) {
            try {
                $record->status->transitionTo(
                    DataComplete::class,
                    transitionedBy: Auth::id(),
                    notes: $data['notes'] ?? null
                );

                Notification::make()
                    ->title(__('admin.application.actions.revert_to_data_complete_success'))
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
