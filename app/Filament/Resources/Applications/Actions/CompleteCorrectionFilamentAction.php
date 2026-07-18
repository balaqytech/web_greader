<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Corrections\CompleteCorrectionAction;
use App\Filament\Resources\Applications\Actions\Concerns\NotifiesDomainErrors;
use App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord;
use App\Models\Application;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\CorrectionRequested;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CompleteCorrectionFilamentAction extends Action
{
    use NotifiesDomainErrors;
    use RefreshesApplicationRecord;

    public static function getDefaultName(): ?string
    {
        return 'complete_correction';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize('completeCorrection');

        $this->label(__('admin.application.actions.complete_correction'));
        $this->icon('heroicon-o-check-badge');
        $this->color('success');
        $this->modalHeading(__('admin.application.actions.complete_correction'));
        $this->modalSubmitActionLabel(__('admin.application.actions.complete_correction'));

        $this->schema(function (Schema $schema, Application $record): Schema {
            $checklist = $record->openCorrection?->checklist ?? [];

            $options = [];
            foreach ($checklist as $index => $entry) {
                $options[$index] = $entry['item'] ?? '';
            }

            return $schema->components([
                CheckboxList::make('completed')
                    ->label(__('admin.application.correction_checklist'))
                    ->hint(__('admin.application.correction_complete_all_items'))
                    ->options($options)
                    ->required(),
                Textarea::make('notes')
                    ->label(__('admin.application.notes'))
                    ->rows(2),
            ]);
        });

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof CorrectionRequested
                && $record->openCorrection()->exists()
        );

        $this->action(function (Application $record, array $data, ?Component $livewire) {
            Gate::authorize('completeCorrection', $record);

            try {
                $fresh = app(CompleteCorrectionAction::class)->handle(
                    $record,
                    Auth::user(),
                    $data['completed'] ?? [],
                    $data['notes'] ?? null,
                );

                $this->refreshLivewireRecord($fresh, $livewire);

                Notification::make()
                    ->title($fresh->status instanceof AwaitingContractSignature
                        ? __('admin.application.actions.complete_correction_success_resign')
                        : __('admin.application.actions.complete_correction_success_review'))
                    ->success()
                    ->send();
            } catch (\Throwable $e) {
                $this->notifyDomainFailure($e);
            }
        });
    }
}
