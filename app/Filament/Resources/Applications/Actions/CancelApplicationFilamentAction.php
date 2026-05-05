<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\Cancelled;
use App\States\Applications\Draft;
use App\States\Applications\Submitted;
use App\States\Applications\WaitingContractSignature;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class CancelApplicationFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancel_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.cancel'));
        $this->icon('heroicon-o-x-mark');
        $this->color('danger');
        $this->requiresConfirmation();
        $this->modalHeading(__('admin.application.actions.cancel'));
        $this->modalDescription(__('admin.application.cancel_description'));

        $this->schema([
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record !== null
                && ($record->status instanceof Draft
                    || $record->status instanceof Submitted
                    || $record->status instanceof WaitingContractSignature)
                && ($record->status->canTransitionTo(Cancelled::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            try {
                $record->status->transitionTo(Cancelled::class, $data['notes']);

                Notification::make()
                    ->title(__('admin.application.actions.cancel_success'))
                    ->warning()
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
