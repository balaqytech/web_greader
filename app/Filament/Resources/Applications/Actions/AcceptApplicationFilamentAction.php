<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord;
use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingBranchReview;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AcceptApplicationFilamentAction extends Action
{
    use RefreshesApplicationRecord;

    public static function getDefaultName(): ?string
    {
        return 'accept_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('accept');

        $this->label(__('admin.application.actions.accept'));
        $this->icon('heroicon-o-check-circle');
        $this->color('success');
        $this->modal();
        $this->modalHeading(__('admin.application.actions.accept'));
        $this->modalSubmitActionLabel(__('admin.application.actions.accept'));

        $this->schema([
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof AwaitingBranchReview
                && ($record->status->canTransitionTo(Accepted::class) ?? false)
        );

        $this->action(function (Application $record, array $data, ?Component $livewire) {
            Gate::authorize('accept', $record);

            try {
                $fresh = $record->status->transitionTo(Accepted::class, $data['notes']);

                $this->refreshLivewireRecord($fresh, $livewire);

                Notification::make()
                    ->title(__('admin.application.actions.accept_success'))
                    ->success()
                    ->send();
            } catch (\Exception $exception) {
                Notification::make()
                    ->title($exception->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
