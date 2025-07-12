<?php

namespace App\Enums;

enum InstallmentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
}
