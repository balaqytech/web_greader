<?php

namespace App\Enums;

enum LeadSource: string
{
    case WEBSITE = 'website';
    case WHATSAPP_BOT = 'whatsapp_bot';
}
