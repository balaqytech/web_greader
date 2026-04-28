<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\AcceptApplicationAction;
use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\UnderReview;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class AcceptApplicationFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'accept_application';
    }

    protected function setUp(): void
    {
        parent::setUp();

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
            fn(?Application $record): bool => $record?->status instanceof UnderReview
                && ($record->status->canTransitionTo(Accepted::class) ?? false)
        );

        $this->action(function (Application $record, array $data) {
            try {
                app(AcceptApplicationAction::class)->execute($record, Auth::id(), $data['notes'] ?? null);

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
