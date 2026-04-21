<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;


enum BotContactStatusEnum: string implements HasLabel, HasColor
{
    case NEW = 'new';
    case LEAD = 'lead';
    case APPLICATION = 'application';
    case GUARDIAN = 'guardian';
    case AFFILIATE = 'affiliate';
    case EMPLOYEE = 'employee';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NEW => __('admin.bot_contact.statuses.new'),
            self::LEAD => __('admin.bot_contact.statuses.lead'),
            self::APPLICATION => __('admin.bot_contact.statuses.application'),
            self::GUARDIAN => __('admin.bot_contact.statuses.guardian'),
            self::AFFILIATE => __('admin.bot_contact.statuses.affiliate'),
            self::EMPLOYEE => __('admin.bot_contact.statuses.employee'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEW => 'gray',
            self::LEAD => 'info',
            self::APPLICATION => 'success',
            self::GUARDIAN => 'warning',
            self::AFFILIATE => 'danger',
            self::EMPLOYEE => 'black',
        };
    }
}
