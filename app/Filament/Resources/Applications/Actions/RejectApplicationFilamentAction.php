<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\RejectApplicationAction;
use App\Models\Application;
use App\States\Applications\Rejected;
use App\States\Applications\UnderReview;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

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
            fn (?Application $record): bool => $record?->status instanceof UnderReview
                && ($record->status->canTransitionTo(Rejected::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            app(RejectApplicationAction::class)->execute($record, $data['rejection_reason'], Auth::id());

            Notification::make()
                ->title(__('admin.application.actions.reject_success'))
                ->danger()
                ->send();
        });
    }
}
