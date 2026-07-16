<?php

declare(strict_types=1);

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\Payments\InitiatePaymentAction;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentInitiationException;
use App\Models\Application;
use App\Models\Payment;
use App\States\Applications\AwaitingRegistrationFee;
use App\Support\Settings\PaymentSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Starts a registration-fee attempt for a fee-awaiting application. The only staff-facing way
 * to create one — the chatbot API is the other, and both go through
 * `InitiatePaymentAction`, so the same fee-gating, idempotency, and one-active-attempt rules
 * apply regardless of who is initiating.
 *
 * Deliberately does not settle anything: for Thawani it only opens a checkout session, for
 * bank transfer and cash it only creates the pending attempt. Advancing the application still
 * requires a real, verified payment — this action cannot be used to skip that.
 */
class InitiatePaymentFilamentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'initiate_payment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.payment.actions.initiate'));
        $this->icon('heroicon-o-banknotes');
        $this->color('primary');
        $this->modalHeading(__('admin.payment.actions.initiate'));

        $this->schema([
            Select::make('method')
                ->label(__('admin.payment.method'))
                ->required()
                ->options(fn (): array => $this->availableMethods()),
        ]);

        $this->visible(
            fn (?Application $record): bool => $record instanceof Application
                && $record->status instanceof AwaitingRegistrationFee
                && Gate::allows('create', [Payment::class, $record])
        );

        $this->action(function (Application $record, array $data) {
            Gate::authorize('create', [Payment::class, $record]);

            $method = PaymentMethod::from($data['method']);

            try {
                $payment = app(InitiatePaymentAction::class)->execute(new InitiatePaymentDTO(
                    application: $record,
                    method: $method,
                    actor: Auth::user(),
                ));

                $notification = Notification::make()
                    ->title(__('admin.payment.actions.initiate_success', ['reference' => $payment->reference]))
                    ->success();

                if (($checkoutUrl = $payment->safeCheckoutUrl()) !== null) {
                    $notification
                        ->body($checkoutUrl)
                        ->persistent()
                        ->actions([
                            Action::make('open_checkout')
                                ->label(__('admin.payment.actions.open_checkout'))
                                ->button()
                                ->url($checkoutUrl, shouldOpenInNewTab: true),
                        ]);
                }

                $notification->send();
            } catch (PaymentInitiationException|PaymentGatewayException $e) {
                Notification::make()
                    ->title(__('admin.payment.actions.initiate_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function availableMethods(): array
    {
        $settings = app(PaymentSettings::class);

        $options = [
            PaymentMethod::THAWANI->value => PaymentMethod::THAWANI->getLabel(),
            PaymentMethod::CASH->value => PaymentMethod::CASH->getLabel(),
        ];

        if ($settings->isBankTransferConfigured()) {
            $options[PaymentMethod::BANK_TRANSFER->value] = PaymentMethod::BANK_TRANSFER->getLabel();
        }

        return $options;
    }
}
