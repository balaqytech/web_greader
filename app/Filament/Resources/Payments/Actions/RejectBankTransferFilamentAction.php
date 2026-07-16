<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Exceptions\StalePaymentStateException;
use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Rejected;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Central finance deciding an uploaded bank receipt does not hold up. A human decision, always
 * carrying a reason — see `AwaitingVerificationToRejected`, which refuses a blank one before
 * any state changes.
 */
class RejectBankTransferFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reject_bank_transfer';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.payment.actions.reject_bank_transfer'));
        $this->icon('heroicon-o-x-circle');
        $this->color('danger');

        $this->schema([
            Textarea::make('reason')
                ->label(__('admin.payment.actions.reject_reason'))
                ->required(),
        ]);

        $this->visible(
            fn (?Payment $record): bool => $record instanceof Payment
                && $record->status instanceof AwaitingVerification
                && Gate::allows('verifyBankTransfer', $record)
        );

        $this->action(function (Payment $record, array $data) {
            Gate::authorize('verifyBankTransfer', $record);

            try {
                $record->status->transitionTo(Rejected::class, $data['reason'], Auth::user());

                Notification::make()
                    ->title(__('admin.payment.actions.reject_bank_transfer_success'))
                    ->success()
                    ->send();
            } catch (StalePaymentStateException $e) {
                Notification::make()
                    ->title(__('admin.payment.actions.reject_bank_transfer_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
