<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidOmrAmountException extends Exception
{
    public static function malformed(string $value): self
    {
        return new self(sprintf(
            'OMR amounts must be a plain decimal string with exactly three decimal places, got "%s".',
            $value
        ));
    }

    public static function tooLarge(string $value, int $maxWholeOmr): self
    {
        return new self(sprintf(
            'OMR amount "%s" exceeds the supported maximum of %d OMR.',
            $value,
            $maxWholeOmr
        ));
    }

    public static function negativeBaisa(int $baisa): self
    {
        return new self(sprintf('OMR amounts cannot be negative, got %d baisa.', $baisa));
    }
}
