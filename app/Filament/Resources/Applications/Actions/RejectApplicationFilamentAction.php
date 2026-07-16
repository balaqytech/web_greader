<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\Rejected;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RejectApplicationFilamentAction extends Action
{
    use RefreshesApplicationRecord;

    public static function getDefaultName(): ?string
    {
        return 'reject_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('reject');

        $this->label(__('admin.application.actions.reject'));
        $this->icon('heroicon-o-x-circle');
        $this->color('danger');
        $this->modal();
        $this->modalHeading(__('admin.application.actions.reject'));
        $this->modalSubmitActionLabel(__('admin.application.actions.reject'));

        $this->schema([
            Textarea::make('rejection_reason')
                ->label(__('admin.application.rejection_reason'))
                ->required()
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof AwaitingBranchReview
                && ($record->status->canTransitionTo(Rejected::class) ?? false)
        );

        $this->action(function (Application $record, array $data, ?Component $livewire) {
            Gate::authorize('reject', $record);

            try {
                $fresh = $record->status->transitionTo(Rejected::class, $data['rejection_reason']);

                $this->refreshLivewireRecord($fresh, $livewire);

                Notification::make()
                    ->title(__('admin.application.actions.reject_success'))
                    ->danger()
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
