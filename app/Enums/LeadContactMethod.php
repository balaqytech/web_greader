<?php

namespace App\Enums;

enum LeadContactMethod: string
{
    case Call = 'call';
    case Whatsapp = 'whatsapp';
    case Visit = 'visit';
    case Other = 'other';
}
