<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstallmentStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case OVERDUE = 'overdue';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('admin.installment.statuses.pending'),
            self::PAID => __('admin.installment.statuses.paid'),
            self::OVERDUE => __('admin.installment.statuses.overdue'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
        };
    }
}
