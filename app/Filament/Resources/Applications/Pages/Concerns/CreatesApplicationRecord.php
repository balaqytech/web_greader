<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Actions\Applications\CreateApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Source;
use App\Models\Program;
use App\Models\Season;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

trait CreatesApplicationRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        $program = Program::findOrFail($data['program_id']);

        $data['season_id'] = Season::current($program->type)->id;
        $data['source'] = Source::DASHBOARD;

        $dto = CreateApplicationDTO::fromFormData($data);
        $application = app(CreateApplicationAction::class)->execute($dto);

        // Applications always start at the registration-fee gate (the model default).
        // Advancing past it is payment-gated and handled in the payments phase.
        return $application->fresh();
    }

    /**
     * One accurate baseline create action. The old "create as draft" / "create as
     * submitted" pair was misleading — neither the draft nor submitted state exists, and
     * advancing past the fee gate is payment-gated (Phase 2).
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label(__('admin.application.actions.create')),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title(__('admin.application.actions.create_success'))
            ->success();
    }
}
