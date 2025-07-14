<?php

namespace App\Enums;

enum PaymentMethod: string implements \Filament\Support\Contracts\HasLabel
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case THAWANI = 'thawani';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::THAWANI => 'Thawani',
        };
    }

    public function colors(): string
    {
        return match ($this) {
            self::CASH => 'gray',
            self::BANK_TRANSFER => 'info',
            self::THAWANI => 'success',
        };
    }
}
