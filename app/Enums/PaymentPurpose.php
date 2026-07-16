<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a payment is for. Only the registration fee exists today; the column is modelled as
 * an enum rather than assumed so a second fee type (tuition, materials) can be added without
 * a schema change or an ambiguous "which payment satisfies the fee gate?" question — the
 * application gate only ever looks for a paid REGISTRATION_FEE.
 */
enum PaymentPurpose: string implements HasColor, HasLabel
{
    case REGISTRATION_FEE = 'registration_fee';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::REGISTRATION_FEE => __('admin.payment.purposes.registration_fee'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REGISTRATION_FEE => 'primary',
        };
    }
}
