<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ReturnToSubmittedFilamentAction extends Action
{
    use RefreshesApplicationRecord;

    public static function getDefaultName(): ?string
    {
        return 'return_to_submitted';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('reopen');

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

        $this->action(function (Application $record, array $data, ?Component $livewire) {
            Gate::authorize('reopen', $record);

            try {
                $fresh = $record->status->transitionTo(AwaitingApplicationCompletion::class, $data['notes']);

                $this->refreshLivewireRecord($fresh, $livewire);

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
