<?php

namespace App\Exceptions;

use Exception;

class ContractTokenExpiredException extends Exception
{
    protected $message = 'The contract token has expired.';
}
