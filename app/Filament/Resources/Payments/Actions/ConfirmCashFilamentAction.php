<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Exceptions\StalePaymentStateException;
use App\Models\Payment;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\Support\Payments\Evidence\CashSettlementEvidence;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The only way a cash attempt reaches Paid — a staff member physically received the money and
 * is recording it. `ConfirmCash:Payment` is deliberately unassigned to every ordinary role
 * (including central finance), since this marks a fee paid with no verifiable money movement
 * behind it.
 */
class ConfirmCashFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'confirm_cash';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.payment.actions.confirm_cash'));
        $this->icon('heroicon-o-banknotes');
        $this->color('success');

        $this->schema([
            TextInput::make('cash_reference')
                ->label(__('admin.payment.actions.cash_reference'))
                ->required(),
            Textarea::make('cash_notes')
                ->label(__('admin.payment.actions.cash_notes'))
                ->required(),
        ]);

        $this->visible(
            fn (?Payment $record): bool => $record instanceof Payment
                && $record->status instanceof Pending
                && Gate::allows('confirmCash', $record)
        );

        $this->action(function (Payment $record, array $data) {
            Gate::authorize('confirmCash', $record);

            try {
                $record->status->transitionTo(Paid::class, new CashSettlementEvidence(
                    confirmedBy: Auth::user(),
                    reference: $data['cash_reference'],
                    notes: $data['cash_notes'],
                ));

                Notification::make()
                    ->title(__('admin.payment.actions.confirm_cash_success'))
                    ->success()
                    ->send();
            } catch (StalePaymentStateException $e) {
                Notification::make()
                    ->title(__('admin.payment.actions.confirm_cash_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
