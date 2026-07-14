<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Actions\Leads\CreateLeadWithApplicationAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

trait CreatesApplicationRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        // Manual entry always creates the lead first (CreateLeadWithApplicationAction), in
        // the same transaction as the application — this page never constructs an
        // application on its own. Applications always start at the registration-fee gate
        // (the model default); advancing past it is payment-gated and handled in the
        // payments phase.
        $application = app(CreateLeadWithApplicationAction::class)->execute($data);

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
