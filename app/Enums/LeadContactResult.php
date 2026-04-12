<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadContactResult: string implements HasLabel
{
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case NoResponse = 'no_response';
    case FollowUpLater = 'follow_up_later';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Interested => __('admin.lead.contact_results.interested'),
            self::NotInterested => __('admin.lead.contact_results.not_interested'),
            self::NoResponse => __('admin.lead.contact_results.no_response'),
            self::FollowUpLater => __('admin.lead.contact_results.follow_up_later'),
        };
    }
}
