<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CancelApplicationFilamentAction extends Action
{
    use RefreshesApplicationRecord;

    public static function getDefaultName(): ?string
    {
        return 'cancel_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('cancel');

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
                ->required()
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record !== null
                && ($record->status instanceof AwaitingRegistrationFee
                    || $record->status instanceof AwaitingApplicationCompletion
                    || $record->status instanceof AwaitingContractSignature
                    || $record->status instanceof AwaitingBranchReview
                    || $record->status instanceof CorrectionRequested)
                && ($record->status->canTransitionTo(Cancelled::class) ?? false)
        );

        $this->action(function (Application $record, array $data, ?Component $livewire) {
            Gate::authorize('cancel', $record);

            try {
                $fresh = $record->status->transitionTo(Cancelled::class, $data['notes']);

                $this->refreshLivewireRecord($fresh, $livewire);

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
