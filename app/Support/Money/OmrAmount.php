<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Exceptions\InvalidOmrAmountException;
use Stringable;

/**
 * An exact Omani Rial amount, held as a canonical decimal string with exactly three decimal
 * places (OMR's real precision: 1 OMR = 1000 baisa).
 *
 * Every arithmetic path here is integer-based. Floating point is never used, because binary
 * floats cannot represent decimal money exactly — `0.1 + 0.2 !== 0.3` — and a fee that is
 * off by one baisa against the provider is a payment that reconciles to nothing. The
 * canonical string is what we persist and compare; baisa integers are what Thawani is
 * given. Both directions are lossless and round-trip exactly.
 *
 * Instances are immutable and always non-negative; a valid instance may still be zero, so
 * callers that require a real charge must check `isPositive()`.
 */
final readonly class OmrAmount implements Stringable
{
    /**
     * OMR's minor unit. 1 OMR = 1000 baisa (three decimal places).
     */
    public const BAISA_PER_OMR = 1000;

    /**
     * Bounds the whole part so `toBaisa()` can never overflow a 64-bit int, and so an
     * absurd fee (a typo of many digits) is rejected at the boundary rather than persisted.
     */
    public const MAX_WHOLE_OMR = 999_999_999;

    /**
     * Canonical form only: no sign, no leading zeros beyond a bare "0", exactly three
     * decimals. Anything else is a caller bug or bad input, not something to coerce —
     * silently reinterpreting "25.5" as 25.500 would hide a real mistake about precision.
     */
    private const CANONICAL_PATTERN = '/^(0|[1-9]\d*)\.\d{3}$/';

    private function __construct(public string $value) {}

    /**
     * @throws InvalidOmrAmountException
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::CANONICAL_PATTERN, $value) !== 1) {
            throw InvalidOmrAmountException::malformed($value);
        }

        [$whole] = explode('.', $value, 2);

        if ((int) $whole > self::MAX_WHOLE_OMR) {
            throw InvalidOmrAmountException::tooLarge($value, self::MAX_WHOLE_OMR);
        }

        return new self($value);
    }

    /**
     * @throws InvalidOmrAmountException
     */
    public static function fromBaisa(int $baisa): self
    {
        if ($baisa < 0) {
            throw InvalidOmrAmountException::negativeBaisa($baisa);
        }

        return self::fromString(sprintf(
            '%d.%03d',
            intdiv($baisa, self::BAISA_PER_OMR),
            $baisa % self::BAISA_PER_OMR
        ));
    }

    /**
     * True when the string is a well-formed OMR amount. Use before `fromString()` in
     * validation contexts where an exception would be the wrong control flow.
     */
    public static function isValid(string $value): bool
    {
        try {
            self::fromString($value);

            return true;
        } catch (InvalidOmrAmountException) {
            return false;
        }
    }

    /**
     * The integer minor-unit amount handed to the provider. Exact by construction: the
     * fractional part is always exactly three digits, so no rounding decision exists here.
     */
    public function toBaisa(): int
    {
        [$whole, $fraction] = explode('.', $this->value, 2);

        return ((int) $whole) * self::BAISA_PER_OMR + (int) $fraction;
    }

    public function isPositive(): bool
    {
        return $this->toBaisa() > 0;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
