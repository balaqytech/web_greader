<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Applications\UploadSignedContractAction;
use App\Models\Application;
use App\States\Applications\DataComplete;
use App\States\Applications\WaitingContract;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

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
            fn(?Application $record): bool => $record?->status instanceof WaitingContract
        );

        $this->action(function (Application $record, array $data) {
            try {
                app(UploadSignedContractAction::class)->execute($record, $data['contract_file'], Auth::id());

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
