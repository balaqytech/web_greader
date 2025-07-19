<?php

namespace App\ValueObjects;

use Illuminate\Support\Number;

class Money
{
    public function __construct(
        public readonly float|int $amount,
        public readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException(__('app.negative_amount_error'));
        }
    }

    public static function from(float|int $amount, ?string $currency = null): self
    {
        $currency = $currency ?? config('app.currency');
        return new self($amount, $currency);
    }

    public function value(): float
    {
        return $this->amount;
    }

    public function __toString(): string
    {
        return Number::currency($this->amount, $this->currency, app()->getLocale());
    }
}