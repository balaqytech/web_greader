<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case THAWANI = 'thawani';
    case BANK_TRANSFER = 'bank_transfer';
    case CASH = 'cash';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::THAWANI => __('admin.payment.methods.thawani'),
            self::BANK_TRANSFER => __('admin.payment.methods.bank_transfer'),
            self::CASH => __('admin.payment.methods.cash'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::THAWANI => 'info',
            self::BANK_TRANSFER => 'warning',
            self::CASH => 'gray',
        };
    }

    /**
     * Whether reaching `paid` for this method requires a human decision rather than a
     * verifiable external confirmation. Both of these mark a fee paid without money moving
     * through a channel the system can independently check, which is why each is gated by
     * its own permission.
     */
    public function requiresManualConfirmation(): bool
    {
        return match ($this) {
            self::BANK_TRANSFER, self::CASH => true,
            self::THAWANI => false,
        };
    }

    /**
     * Cash is a staff-only, in-person method. It is never offered through the chatbot API —
     * a remote caller cannot hand over cash, so exposing it there would only ever be a way
     * to mark a fee paid without money moving.
     */
    public function isAvailableToChatbot(): bool
    {
        return $this !== self::CASH;
    }
}
