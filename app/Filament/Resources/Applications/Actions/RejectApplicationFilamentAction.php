<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\Rejected;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RejectApplicationFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reject_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->action(function (Application $record, array $data) {
            try {
                // Set rejection reason before transitioning
                $record->update(['rejection_reason' => $data['rejection_reason']]);
                $record->status->transitionTo(Rejected::class);

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
