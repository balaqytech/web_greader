<?php

namespace App\ValueObjects;

use Illuminate\Support\Number;

class Money
{
    public function __construct(
        public readonly float|int $amount,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException(__('app.negative_amount_error'));
        }
    }

    public static function from(float|int $amount): self
    {
        return new self($amount);
    }

    public function value(): float
    {
        return $this->amount;
    }

    public function __toString(): string
    {
        return Number::currency($this->amount, config('app.currency'), app()->getLocale());
    }
}