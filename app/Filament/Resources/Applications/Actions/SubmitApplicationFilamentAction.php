<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * Advances an application off the registration-fee gate. The driving transition
 * (AwaitingRegistrationFee -> AwaitingApplicationCompletion) is payment-gated and is
 * registered in the payments phase (Phase 2); until then this action stays hidden
 * because `canTransitionTo` is false.
 */
class SubmitApplicationFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'submit_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('update');

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
            fn (?Application $record): bool => $record?->status instanceof AwaitingRegistrationFee
                && ($record->status->canTransitionTo(AwaitingApplicationCompletion::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            Gate::authorize('update', $record);

            try {
                $record->status->transitionTo(
                    AwaitingApplicationCompletion::class,
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
