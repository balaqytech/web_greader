<?php

namespace App\Filament\Resources\Applications\Actions;

use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Models\Application;
use App\States\Applications\Draft;
use App\States\Applications\Submitted;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class SubmitApplicationFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'submit_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.submit'));
        $this->icon('heroicon-o-paper-airplane');
        $this->color('success');
        $this->requiresConfirmation();
        $this->modalHeading(__('admin.application.actions.submit'));
        $this->modalDescription(__('admin.application.submit_description'));

        $this->schema([
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3)
                ->maxLength(255),
        ]);

        $this->visible(
            fn(?Application $record): bool => $record?->status instanceof Draft
                && ($record->status->canTransitionTo(Submitted::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            try {
                $record->status->transitionTo(
                    Submitted::class,
                    UpdateApplicationDataDTO::fromValidated($record->fresh()->toArray()),
                    $data['notes']
                );

                Notification::make()
                    ->title(__('admin.application.actions.submit_success'))
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
