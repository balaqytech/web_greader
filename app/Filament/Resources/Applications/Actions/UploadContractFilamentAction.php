<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Models\Application;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;

class UploadContractFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'upload_contract';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.application.actions.upload_signed_contract'));
        $this->icon('heroicon-o-arrow-up-tray');
        $this->color('success');
        $this->modalHeading(__('admin.application.actions.upload_signed_contract'));

        $this->schema([
            FileUpload::make('contract_file')
                ->label(__('admin.application.contract_file'))
                ->disk('public')
                ->required()
                ->directory('contracts/uploads')
                ->acceptedFileTypes(['image/*', 'application/pdf'])
                ->maxSize(5120),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record?->status instanceof WaitingContractSignature
        );

        $this->action(function (Application $record, array $data) {
            try {
                // Store file path on the ApplicationContract
                if ($record->contract) {
                    $record->contract->update([
                        'file_path' => $data['contract_file'],
                        'signed_at' => now(),
                        'signed_by_applicant' => false,
                    ]);
                }

                // Transition to UnderReview
                $record->status->transitionTo(UnderReview::class);

                Notification::make()
                    ->title(__('admin.application.actions.upload_signed_contract_success'))
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title(__('admin.application.actions.upload_signed_contract_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
