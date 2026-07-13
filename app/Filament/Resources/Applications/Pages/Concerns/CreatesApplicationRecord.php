<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Actions\Applications\CreateApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Source;
use App\Models\Program;
use App\Models\Season;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

trait CreatesApplicationRecord
{
    public bool $submitAsDraft = true;

    public function createAsDraft(): void
    {
        $this->submitAsDraft = true;
        $this->create();
    }

    public function createAsSubmitted(): void
    {
        $this->submitAsDraft = false;
        $this->create();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $program = Program::findOrFail($data['program_id']);

        $data['season_id'] = Season::current($program->type)->id;
        $data['source'] = Source::DASHBOARD;

        $dto = CreateApplicationDTO::fromFormData($data);
        $application = app(CreateApplicationAction::class)->execute($dto);

        // Applications always start at the registration-fee gate (the model default).
        // Advancing past it is payment-gated and handled in the payments phase, so no
        // forward transition is attempted here regardless of the chosen create button.

        return $application->fresh();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateAsSubmittedAction(),
            $this->getCreateAsDraftAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateAsSubmittedAction(): Action
    {
        return Action::make('createAsSubmitted')
            ->label(__('admin.application.actions.create_as_submitted'))
            ->icon('heroicon-o-paper-airplane')
            ->action('createAsSubmitted')
            ->color('primary')
            ->keyBindings(['mod+s']);
    }

    protected function getCreateAsDraftAction(): Action
    {
        return Action::make('createAsDraft')
            ->label(__('admin.application.actions.create_as_draft'))
            ->icon('heroicon-o-document')
            ->action('createAsDraft')
            ->color('gray');
    }

    protected function getCancelFormAction(): Action
    {
        $url = $this->previousUrl ?? $this->getResourceUrl();

        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/create-record.form.actions.cancel.label'))
            ->alpineClickHandler(
                FilamentView::hasSpaMode($url)
                    ? 'document.referrer ? window.history.back() : Livewire.navigate('.Js::from($url).')'
                    : 'document.referrer ? window.history.back() : (window.location.href = '.Js::from($url).')',
            )
            ->color('gray');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $title = $this->submitAsDraft
            ? __('admin.application.actions.created_as_draft')
            : __('admin.application.actions.create_success');

        return Notification::make()
            ->title($title)
            ->success();
    }
}
