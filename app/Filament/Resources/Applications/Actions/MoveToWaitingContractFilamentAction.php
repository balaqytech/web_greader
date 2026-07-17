<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Documents\EvaluateDocumentRequirementsAction;
use App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord;
use App\Models\Application;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use App\Support\Documents\LogicalRequirement;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class MoveToWaitingContractFilamentAction extends Action
{
    use RefreshesApplicationRecord;

    public static function getDefaultName(): ?string
    {
        return 'move_to_waiting_contract';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('generateContract');

        $this->label(__('admin.application.actions.move_to_waiting_contract'));
        $this->icon('heroicon-o-paper-airplane');
        $this->color('primary');
        $this->requiresConfirmation();

        $this->modalHeading(__('admin.application.actions.move_to_waiting_contract'));
        $this->modalDescription(__('admin.application.move_to_waiting_contract_description'));
        $this->modalSubmitActionLabel(__('admin.application.actions.move_to_waiting_contract'));

        $this->schema([
            // A non-blocking heads-up: missing or rejected documents are surfaced but never
            // prevent contract generation, which proceeds exactly as before.
            Callout::make(__('admin.document.warning.contract_heading'))
                ->warning()
                ->icon('heroicon-o-exclamation-triangle')
                ->description(fn (Application $record): string => $this->outstandingDocumentsDescription($record))
                ->visible(fn (Application $record): bool => app(EvaluateDocumentRequirementsAction::class)
                    ->execute($record)
                    ->hasWarnings()),
            Textarea::make('notes')
                ->label(__('admin.application.notes'))
                ->placeholder(__('admin.application.notes_placeholder'))
                ->rows(3)
                ->maxLength(255),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof AwaitingApplicationCompletion
                && ($record->status->canTransitionTo(AwaitingContractSignature::class) ?? false)
        );

        $this->action(function (Application $record, array $data, ?Component $livewire) {
            Gate::authorize('generateContract', $record);

            try {
                $fresh = $record->status->transitionTo(AwaitingContractSignature::class, $data['notes']);

                $this->refreshLivewireRecord($fresh, $livewire);

                Notification::make()
                    ->title(__('admin.application.actions.move_to_waiting_contract_success'))
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

    private function outstandingDocumentsDescription(Application $record): string
    {
        $labels = app(EvaluateDocumentRequirementsAction::class)
            ->execute($record)
            ->warnings()
            ->map(fn (LogicalRequirement $requirement): string => $requirement->label)
            ->all();

        return __('admin.document.warning.contract_body').' '.implode('، ', $labels);
    }
}
