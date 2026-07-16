<?php

declare(strict_types=1);

use App\Exceptions\PaymentGatewayException;
use App\Services\Payments\ThawaniConfig;

function withThawaniConfig(array $overrides = []): void
{
    config(['thawani' => array_replace_recursive([
        'mode' => 'test',
        'test' => [
            'base_url' => 'https://uatcheckout.thawani.om/api/v1',
            'checkout_base_url' => 'https://uatcheckout.thawani.om/pay',
            'secret_key' => 'test_secret',
            'publishable_key' => 'test_publishable',
        ],
        'live' => [
            'base_url' => 'https://checkout.thawani.om/api/v1',
            'checkout_base_url' => 'https://checkout.thawani.om/pay',
            'secret_key' => 'live_secret',
            'publishable_key' => 'live_publishable',
        ],
    ], $overrides)]);
}

it('builds from valid configuration', function () {
    withThawaniConfig();

    $config = ThawaniConfig::fromConfig();

    expect($config->mode)->toBe('test')
        ->and($config->secretKey)->toBe('test_secret')
        ->and($config->publishableKey)->toBe('test_publishable')
        ->and($config->isLive())->toBeFalse();
});

it('selects the credentials for the active mode', function () {
    withThawaniConfig(['mode' => 'live']);

    $config = ThawaniConfig::fromConfig();

    expect($config->secretKey)->toBe('live_secret')
        ->and($config->isLive())->toBeTrue();
});

it('rejects an unsupported mode', function (mixed $mode) {
    withThawaniConfig(['mode' => $mode]);

    expect(fn () => ThawaniConfig::fromConfig())->toThrow(PaymentGatewayException::class);
})->with(['staging', '', null, 'TEST']);

it('rejects a missing or blank credential rather than guessing', function (string $key) {
    withThawaniConfig(['test' => [$key => null]]);

    expect(fn () => ThawaniConfig::fromConfig())->toThrow(PaymentGatewayException::class);
})->with(['base_url', 'checkout_base_url', 'secret_key', 'publishable_key']);

it('rejects a blank credential string', function () {
    withThawaniConfig(['test' => ['secret_key' => '   ']]);

    expect(fn () => ThawaniConfig::fromConfig())->toThrow(PaymentGatewayException::class);
});

/**
 * The reason this guard exists. `jkbroot/thawani` hardcodes a REAL UAT secret key as its own
 * config default and merges that config in whether or not it was published — so if
 * config/thawani.php is ever deleted or lost in a merge, the application would silently
 * transact against a stranger's UAT merchant. That failure is invisible: requests succeed,
 * checkout pages render, and the money simply is not ours.
 */
it('refuses the secret key bundled inside the vendor package', function () {
    withThawaniConfig(['test' => ['secret_key' => 'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et']]);

    expect(fn () => ThawaniConfig::fromConfig())
        ->toThrow(PaymentGatewayException::class, 'bundled inside the jkbroot/thawani package');
});

it('refuses the publishable key bundled inside the vendor package', function () {
    withThawaniConfig(['test' => ['publishable_key' => 'HGvTMLDssJghr9tlN9gr4DVYt0qyBy']]);

    expect(fn () => ThawaniConfig::fromConfig())
        ->toThrow(PaymentGatewayException::class, 'bundled inside the jkbroot/thawani package');
});

/**
 * The repository's own config/thawani.php must keep overriding the package's merged
 * defaults. If this fails, the hardcoded vendor key is live again.
 */
it('resolves the application config over the package\'s merged defaults', function () {
    expect(config('thawani.test.secret_key'))->not->toBe('rRQ26GcsZzoEhbrP2HZvLYDbn9C9et')
        ->and(config('thawani.test.publishable_key'))->not->toBe('HGvTMLDssJghr9tlN9gr4DVYt0qyBy');
});

it('treats misconfiguration as final, never retryable', function () {
    withThawaniConfig(['test' => ['secret_key' => null]]);

    try {
        ThawaniConfig::fromConfig();
    } catch (PaymentGatewayException $e) {
        expect($e->retryable)->toBeFalse();

        return;
    }

    $this->fail('Expected a PaymentGatewayException.');
});

it('never puts a credential value into the exception message, which reaches logs', function () {
    withThawaniConfig(['test' => ['secret_key' => 'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et']]);

    try {
        ThawaniConfig::fromConfig();
    } catch (PaymentGatewayException $e) {
        expect($e->getMessage())->not->toContain('rRQ26GcsZzoEhbrP2HZvLYDbn9C9et');

        return;
    }

    $this->fail('Expected a PaymentGatewayException.');
});
