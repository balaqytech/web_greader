<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Rules\Payment\PositiveOmrAmount;
use App\Support\Money\OmrAmount;
use App\Support\Settings\PaymentSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/**
 * Configures the global registration fee and the bank-transfer instructions.
 *
 * Guarded by `Manage:PaymentSettings`, which no ordinary role holds — this page decides what
 * every future applicant is charged. `canAccess()` is real authorization, not merely
 * navigation hiding: Filament re-runs it on the initial load and on every subsequent
 * Livewire request.
 *
 * Neither setting has a default. Until the fee is saved, payment creation is blocked
 * everywhere; until the bank instructions are saved, the bank-transfer method stays
 * unavailable. Each setting gates its own method independently.
 *
 * @property-read Schema $form
 */
class PaymentSettingsPage extends Page
{
    protected string $view = 'filament.pages.payment-settings-page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('Manage:PaymentSettings') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.payment_settings');
    }

    public function getTitle(): string
    {
        return __('admin.navigation.payment_settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.financial_and_fees');
    }

    public function mount(): void
    {
        $settings = app(PaymentSettings::class);

        // The raw stored amount, not the parsed one: if the stored value is somehow
        // unusable, the administrator has to be able to see and repair what is actually
        // there rather than be shown an empty field.
        $this->form->fill([
            PaymentSettings::REGISTRATION_FEE_AMOUNT => $settings->rawRegistrationFeeAmount(),
            PaymentSettings::BANK_TRANSFER_INSTRUCTIONS => $settings->bankTransferInstructions(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make(PaymentSettings::REGISTRATION_FEE_AMOUNT)
                        ->label(__('admin.payment_settings.registration_fee_amount'))
                        ->helperText(__('admin.payment_settings.registration_fee_amount_help'))
                        ->required()
                        ->rule(new PositiveOmrAmount),
                    Textarea::make(PaymentSettings::BANK_TRANSFER_INSTRUCTIONS)
                        ->label(__('admin.payment_settings.bank_transfer_instructions'))
                        ->helperText(__('admin.payment_settings.bank_transfer_instructions_help'))
                        ->rows(6)
                        ->maxLength(2000)
                        ->nullable(),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('admin.payment_settings.save'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        // Re-authorize inside the action rather than relying on mount-time access alone:
        // this method writes, and it must never run for a user whose permission was revoked
        // after the page was first loaded.
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        $settings = app(PaymentSettings::class);

        $settings->setRegistrationFee(
            OmrAmount::fromString((string) $data[PaymentSettings::REGISTRATION_FEE_AMOUNT])
        );

        $settings->setBankTransferInstructions(
            $data[PaymentSettings::BANK_TRANSFER_INSTRUCTIONS] ?? null
        );

        Notification::make()
            ->success()
            ->title(__('admin.payment_settings.saved'))
            ->send();
    }
}
