<?php

namespace App\Enums;

enum StudentStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case GRADUATED = 'graduated';
}
