<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Exceptions\StalePaymentStateException;
use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * The branch-side counterpart to the API's receipt upload — same eligibility (a pending bank
 * transfer), same destination disk, same 5 MB PDF/JPEG/PNG limit.
 */
class UploadReceiptFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'upload_receipt';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.payment.actions.upload_receipt'));
        $this->icon('heroicon-o-arrow-up-tray');
        $this->color('primary');
        $this->modalHeading(__('admin.payment.actions.upload_receipt'));

        $this->schema([
            FileUpload::make('receipt')
                ->label(__('admin.payment.receipt'))
                ->disk('local')
                ->directory('receipts')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(5120)
                ->required(),
        ]);

        $this->visible(
            fn (?Payment $record): bool => $record instanceof Payment && Gate::allows('uploadReceipt', $record)
        );

        $this->action(function (Payment $record, array $data) {
            Gate::authorize('uploadReceipt', $record);

            try {
                $record->status->transitionTo(AwaitingVerification::class, $data['receipt']);

                Notification::make()
                    ->title(__('admin.payment.actions.upload_receipt_success'))
                    ->success()
                    ->send();
            } catch (StalePaymentStateException $e) {
                Notification::make()
                    ->title(__('admin.payment.actions.upload_receipt_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
