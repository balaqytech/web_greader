<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Exceptions\InvalidOmrAmountException;
use App\Support\Money\OmrAmount;
use Illuminate\Support\Facades\Log;

/**
 * Typed accessor for the payment-related settings. This is the only sanctioned way to read
 * the registration fee or the bank-transfer instructions.
 *
 * There is deliberately no default fee. Until an authorised administrator saves one,
 * `registrationFee()` returns NULL and payment creation stays blocked — a guessed or zero
 * default would let applications through the fee gate for free, which is worse than an
 * outage.
 */
class PaymentSettings
{
    public const REGISTRATION_FEE_AMOUNT = 'registration_fee_amount';

    public const BANK_TRANSFER_INSTRUCTIONS = 'bank_transfer_instructions';

    /**
     * Every key this domain owns, seeded as NULL so the setting is discoverable in the UI
     * before it is configured.
     *
     * @var list<string>
     */
    public const KEYS = [
        self::REGISTRATION_FEE_AMOUNT,
        self::BANK_TRANSFER_INSTRUCTIONS,
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * The configured fee, or NULL when unset — or when the stored value is unusable.
     *
     * Malformed storage reports NULL rather than throwing, for two reasons: blocking
     * payments is the safe direction, and a throwing read accessor would break the very
     * settings page an administrator needs in order to repair the value, making the
     * corruption unfixable through the UI. The warning keeps it observable instead of
     * silent. Writes go through `setRegistrationFee()`, which only accepts a validated
     * `OmrAmount`, so this branch should be unreachable in practice.
     */
    public function registrationFee(): ?OmrAmount
    {
        $raw = $this->settings->get(self::REGISTRATION_FEE_AMOUNT);

        if ($raw === null) {
            return null;
        }

        try {
            $amount = OmrAmount::fromString($raw);
        } catch (InvalidOmrAmountException $e) {
            Log::warning('Stored registration fee is not a valid OMR amount; treating the fee as unconfigured and blocking payment creation.', [
                'key' => self::REGISTRATION_FEE_AMOUNT,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $amount->isPositive()) {
            Log::warning('Stored registration fee is not positive; treating the fee as unconfigured and blocking payment creation.', [
                'key' => self::REGISTRATION_FEE_AMOUNT,
            ]);

            return null;
        }

        return $amount;
    }

    /**
     * The raw stored string, for the settings form to display and repair. Unlike
     * `registrationFee()` this does not hide a malformed value — the administrator needs to
     * see what is actually stored in order to correct it.
     */
    public function rawRegistrationFeeAmount(): ?string
    {
        return $this->settings->get(self::REGISTRATION_FEE_AMOUNT);
    }

    public function isRegistrationFeeConfigured(): bool
    {
        return $this->registrationFee() !== null;
    }

    /**
     * Plain-text instructions shown to a guardian paying by bank transfer. NULL or blank
     * means the bank-transfer method is not available.
     */
    public function bankTransferInstructions(): ?string
    {
        $raw = $this->settings->get(self::BANK_TRANSFER_INSTRUCTIONS);

        return blank($raw) ? null : $raw;
    }

    public function isBankTransferConfigured(): bool
    {
        return $this->bankTransferInstructions() !== null;
    }

    public function setRegistrationFee(OmrAmount $amount): void
    {
        $this->settings->set(self::REGISTRATION_FEE_AMOUNT, $amount->value);
    }

    public function setBankTransferInstructions(?string $instructions): void
    {
        $this->settings->set(
            self::BANK_TRANSFER_INSTRUCTIONS,
            blank($instructions) ? null : $instructions
        );
    }
}
