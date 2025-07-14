<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case CANCELED = 'canceled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('admin.payment.statuses.pending'),
            self::PAID => __('admin.payment.statuses.paid'),
            self::FAILED => __('admin.payment.statuses.failed'),
            self::REFUNDED => __('admin.payment.statuses.refunded'),
            self::CANCELED => __('admin.payment.statuses.canceled'),
        };
    }

    public function colors(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PAID => 'success',
            self::FAILED => 'danger',
            self::REFUNDED => 'warning',
            self::CANCELED => 'danger',
        };
    }
}
