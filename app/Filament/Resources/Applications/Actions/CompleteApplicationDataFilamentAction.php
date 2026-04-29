<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\UpdateApplicationDataAction;
use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Filament\Resources\Applications\Schemas\ApplicationForm;
use App\Models\Application;
use App\States\Applications\DataComplete;
use App\States\Applications\PendingRegistration;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

class CompleteApplicationDataFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'complete_data';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.complete_data'));
        $this->icon('heroicon-o-check-circle');
        $this->color('success');
        
        $this->modalHeading(__('admin.application.actions.complete_data'));
        
        // Populate the modal with the ApplicationForm components
        $schema = ApplicationForm::configure(Schema::make());
        $this->form($schema->getComponents());

        // Fill the form with the current application data
        $this->fillForm(function (Application $record): array {
            return $record->toArray();
        });

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof PendingRegistration
        );

        $this->action(function (Application $record, array $data) {
            try {
                // Update the data
                $dto = UpdateApplicationDataDTO::fromValidated($data);
                app(UpdateApplicationDataAction::class)->execute($record, $dto);

                // Transition the state
                $record->status->transitionTo(
                    DataComplete::class,
                    transitionedBy: auth()->id(),
                    notes: 'Data completed by staff'
                );

                Notification::make()
                    ->title(__('admin.application.actions.complete_data_success'))
                    ->success()
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
