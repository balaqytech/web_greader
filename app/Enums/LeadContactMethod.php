<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadContactMethod: string implements HasLabel
{
    case Call = 'call';
    case Whatsapp = 'whatsapp';
    case Visit = 'visit';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Call => __('admin.lead.contact_methods.call'),
            self::Whatsapp => __('admin.lead.contact_methods.whatsapp'),
            self::Visit => __('admin.lead.contact_methods.visit'),
            self::Other => __('admin.lead.contact_methods.other'),
        };
    }
}
