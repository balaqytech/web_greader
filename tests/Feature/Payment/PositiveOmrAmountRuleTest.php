<?php

declare(strict_types=1);

use App\Rules\Payment\PositiveOmrAmount;
use Illuminate\Support\Facades\Validator;

function validateOmrAmount(mixed $value): Illuminate\Contracts\Validation\Validator
{
    return Validator::make(['amount' => $value], ['amount' => [new PositiveOmrAmount]]);
}

it('passes a canonical positive amount', function (string $value) {
    expect(validateOmrAmount($value)->passes())->toBeTrue();
})->with(['0.001', '25.000', '25.500', '150.750']);

it('fails an amount that is not exactly three decimal places', function (string $value) {
    expect(validateOmrAmount($value)->passes())->toBeFalse();
})->with(['25', '25.5', '25.50', '25.5000', '.500', '25.']);

it('fails a zero amount', function () {
    $validator = validateOmrAmount('0.000');

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('amount'))->toBe(__('validation.omr_amount.positive'));
});

it('fails a negative amount', function (string $value) {
    expect(validateOmrAmount($value)->passes())->toBeFalse();
})->with(['-1.000', '-0.001']);

it('fails a non-numeric or non-scalar value', function (mixed $value) {
    expect(validateOmrAmount($value)->passes())->toBeFalse();
})->with([
    'text' => 'abc',
    'array' => [['25.000']],
    'bool' => true,
    'float' => 25.0,
]);

/**
 * Laravel skips a non-implicit ValidationRule for an empty *string* — emptiness is
 * `required`'s job, not this rule's — but a present NULL still reaches the rule and fails.
 * Pinned deliberately: every caller must pair this rule with `required`, or a blank amount
 * would slip through unvalidated.
 */
it('does not fire on an empty string, which is required\'s job', function () {
    expect(validateOmrAmount('')->passes())->toBeTrue();
});

it('still fails a present null', function () {
    expect(validateOmrAmount(null)->passes())->toBeFalse();
});

it('rejects an empty amount when paired with required, as every caller must pair it', function (mixed $value) {
    $validator = Validator::make(
        ['amount' => $value],
        ['amount' => ['required', new PositiveOmrAmount]]
    );

    expect($validator->passes())->toBeFalse();
})->with(['empty string' => '', 'null' => null]);

it('reports a translated format message', function () {
    $validator = validateOmrAmount('25.5');

    expect($validator->errors()->first('amount'))->toBe(__('validation.omr_amount.format'))
        ->and($validator->errors()->first('amount'))->not->toContain('validation.omr_amount');
});
