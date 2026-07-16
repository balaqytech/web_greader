<?php

declare(strict_types=1);

use App\Exceptions\InvalidOmrAmountException;
use App\Support\Money\OmrAmount;

it('accepts a canonical three-decimal amount', function (string $value) {
    expect(OmrAmount::fromString($value)->value)->toBe($value);
})->with(['0.000', '0.001', '25.000', '25.500', '1.999', '999999999.999']);

it('rejects anything that is not exactly three decimal places', function (string $value) {
    expect(fn () => OmrAmount::fromString($value))
        ->toThrow(InvalidOmrAmountException::class);
})->with([
    'no decimals' => '25',
    'one decimal' => '25.5',
    'two decimals' => '25.50',
    'four decimals' => '25.5000',
    'trailing dot' => '25.',
    'leading dot' => '.500',
    'empty' => '',
    'not a number' => 'abc',
    'whitespace padded' => ' 25.000 ',
    'thousands separator' => '1,000.000',
    'scientific notation' => '2.5e1',
    'hex' => '0x19',
]);

/**
 * Coercing "25.5" to 25.500 would hide a real mistake about what is being charged, so the
 * precision is required rather than inferred.
 */
it('does not coerce a shorter decimal into three places', function () {
    expect(fn () => OmrAmount::fromString('25.5'))->toThrow(InvalidOmrAmountException::class);
});

it('rejects signed amounts', function (string $value) {
    expect(fn () => OmrAmount::fromString($value))
        ->toThrow(InvalidOmrAmountException::class);
})->with(['-25.000', '+25.000', '-0.001']);

it('rejects non-canonical leading zeros', function (string $value) {
    expect(fn () => OmrAmount::fromString($value))
        ->toThrow(InvalidOmrAmountException::class);
})->with(['025.000', '00.500']);

it('rejects an amount beyond the supported range', function () {
    expect(fn () => OmrAmount::fromString('1000000000.000'))
        ->toThrow(InvalidOmrAmountException::class);
});

it('converts to exact integer baisa without floating point', function (string $value, int $baisa) {
    expect(OmrAmount::fromString($value)->toBaisa())->toBe($baisa);
})->with([
    ['0.000', 0],
    ['0.001', 1],
    ['0.010', 10],
    ['0.100', 100],
    ['1.000', 1000],
    ['25.000', 25000],
    ['25.500', 25500],
    ['25.001', 25001],
    ['0.070', 70],
    ['999999999.999', 999999999999],
]);

/**
 * The classic binary-float failure: 0.1 + 0.2 !== 0.3. A fee that is off by one baisa
 * against the provider reconciles to nothing, so these conversions must be integer-exact.
 */
it('converts amounts that binary floats cannot represent exactly', function (string $value, int $baisa) {
    expect(OmrAmount::fromString($value)->toBaisa())->toBe($baisa);
})->with([
    ['0.100', 100],
    ['0.200', 200],
    ['0.300', 300],
    ['0.070', 70],
    ['1.005', 1005],
    ['2.675', 2675],
    ['8.170', 8170],
]);

it('round-trips through baisa losslessly', function (string $value) {
    expect(OmrAmount::fromBaisa(OmrAmount::fromString($value)->toBaisa())->value)->toBe($value);
})->with(['0.000', '0.001', '0.070', '25.000', '25.500', '1.005', '999999999.999']);

it('builds from baisa with zero-padded fractions', function (int $baisa, string $value) {
    expect(OmrAmount::fromBaisa($baisa)->value)->toBe($value);
})->with([
    [0, '0.000'],
    [1, '0.001'],
    [10, '0.010'],
    [100, '0.100'],
    [1000, '1.000'],
    [25500, '25.500'],
]);

it('rejects negative baisa', function () {
    expect(fn () => OmrAmount::fromBaisa(-1))
        ->toThrow(InvalidOmrAmountException::class);
});

it('reports whether an amount is positive', function () {
    expect(OmrAmount::fromString('0.000')->isPositive())->toBeFalse()
        ->and(OmrAmount::fromString('0.001')->isPositive())->toBeTrue()
        ->and(OmrAmount::fromString('25.000')->isPositive())->toBeTrue();
});

it('validates without throwing', function () {
    expect(OmrAmount::isValid('25.000'))->toBeTrue()
        ->and(OmrAmount::isValid('25.5'))->toBeFalse()
        ->and(OmrAmount::isValid('abc'))->toBeFalse();
});

it('compares by exact value', function () {
    expect(OmrAmount::fromString('25.000')->equals(OmrAmount::fromString('25.000')))->toBeTrue()
        ->and(OmrAmount::fromString('25.000')->equals(OmrAmount::fromString('25.001')))->toBeFalse();
});

it('stringifies to its canonical value', function () {
    expect((string) OmrAmount::fromString('25.500'))->toBe('25.500');
});
