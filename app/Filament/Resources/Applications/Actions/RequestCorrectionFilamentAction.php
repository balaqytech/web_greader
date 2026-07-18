<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Corrections\RequestCorrectionAction;
use App\Filament\Resources\Applications\Actions\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\CorrectionRequested;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RequestCorrectionFilamentAction extends Action
{
    use NotifiesDomainErrors;
    use RefreshesApplicationRecord;

    public static function getDefaultName(): ?string
    {
        return 'request_correction';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('requestCorrection');

        $this->label(__('admin.application.actions.request_correction'));
        $this->icon('heroicon-o-clipboard-document-list');
        $this->color('warning');
        $this->modalHeading(__('admin.application.actions.request_correction'));
        $this->modalSubmitActionLabel(__('admin.application.actions.request_correction'));

        $this->schema([
            Textarea::make('reason')
                ->label(__('admin.application.correction_reason'))
                ->required()
                ->rows(3),
            TagsInput::make('items')
                ->label(__('admin.application.correction_checklist_items'))
                ->hint(__('admin.application.correction_checklist_items_hint'))
                ->required(),
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->rows(2),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof AwaitingBranchReview
                && ($record->status->canTransitionTo(CorrectionRequested::class) ?? false)
        );

        $this->action(function (Application $record, array $data, ?Component $livewire) {
            Gate::authorize('requestCorrection', $record);

            try {
                $fresh = app(RequestCorrectionAction::class)->handle(
                    $record,
                    Auth::user(),
                    $data['reason'],
                    $data['items'] ?? [],
                    $data['notes'] ?? null,
                );

                $this->refreshLivewireRecord($fresh, $livewire);

                Notification::make()
                    ->title(__('admin.application.actions.request_correction_success'))
                    ->success()
                    ->send();
            } catch (\Throwable $e) {
                $this->notifyDomainFailure($e);
            }
        });
    }
}
