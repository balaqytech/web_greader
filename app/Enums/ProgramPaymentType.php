<?php

namespace App\Enums;

enum ProgramPaymentType: string
{
    case ONE_TIME = 'one_time';
    case INSTALLMENT = 'installment';
}
