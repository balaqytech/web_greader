<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ReturnToSubmittedFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'return_to_submitted';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.return_to_submitted'));
        $this->icon('heroicon-o-arrow-uturn-left');
        $this->color('warning');
        $this->requiresConfirmation();
        $this->modalHeading(__('admin.application.actions.return_to_submitted'));

        $this->schema([
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof AwaitingContractSignature
                && ($record->status->canTransitionTo(AwaitingApplicationCompletion::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            try {
                $record->status->transitionTo(AwaitingApplicationCompletion::class, $data['notes']);

                Notification::make()
                    ->title(__('admin.application.actions.return_to_submitted_success'))
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
