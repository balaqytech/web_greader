<?php

namespace App\Enums;

enum ProgramPaymentType: string implements \Filament\Support\Contracts\HasLabel
{
    case ONE_TIME = 'one_time';
    case INSTALLMENT = 'installment';

    public function getLabel(): string
    {
        return match ($this) {
            self::ONE_TIME => __('admin.program.payment_types.one_time'),
            self::INSTALLMENT => __('admin.program.payment_types.installments'),
        };
    }
}
