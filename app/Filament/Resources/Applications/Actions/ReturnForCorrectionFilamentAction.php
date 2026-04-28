<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\ReturnApplicationForCorrectionAction;
use App\Models\Application;
use App\States\Applications\PendingRegistration;
use App\States\Applications\UnderReview;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ReturnForCorrectionFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'return_for_correction';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.return_for_correction'));
        $this->icon('heroicon-o-arrow-uturn-left');
        $this->color('warning');
        $this->modal();
        $this->modalHeading(__('admin.application.actions.return_for_correction'));
        $this->modalSubmitActionLabel(__('admin.application.actions.return_for_correction'));

        $this->schema([
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof UnderReview
                && ($record->status->canTransitionTo(PendingRegistration::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            app(ReturnApplicationForCorrectionAction::class)->execute($record, $data['notes'] ?? null, Auth::id());

            Notification::make()
                ->title(__('admin.application.actions.return_for_correction_success'))
                ->warning()
                ->send();
        });
    }
}
