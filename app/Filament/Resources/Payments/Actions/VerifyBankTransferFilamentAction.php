<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Exceptions\StalePaymentStateException;
use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Paid;
use App\Support\Payments\Evidence\BankTransferVerificationEvidence;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Central finance accepting an uploaded bank receipt as a genuine match for the attempt.
 * Settles the payment and advances the application past the fee gate in the same transaction
 * as every other route to Paid (see `SettleRegistrationFee`).
 */
class VerifyBankTransferFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'verify_bank_transfer';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.payment.actions.verify_bank_transfer'));
        $this->icon('heroicon-o-check-circle');
        $this->color('success');
        $this->requiresConfirmation();

        $this->visible(
            fn (?Payment $record): bool => $record instanceof Payment
                && $record->status instanceof AwaitingVerification
                && Gate::allows('verifyBankTransfer', $record)
        );

        $this->action(function (Payment $record) {
            Gate::authorize('verifyBankTransfer', $record);

            try {
                $record->status->transitionTo(Paid::class, new BankTransferVerificationEvidence(Auth::user()));

                Notification::make()
                    ->title(__('admin.payment.actions.verify_bank_transfer_success'))
                    ->success()
                    ->send();
            } catch (StalePaymentStateException $e) {
                Notification::make()
                    ->title(__('admin.payment.actions.verify_bank_transfer_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
