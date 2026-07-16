<?php

declare(strict_types=1);

use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\Drivers\ThawaniGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;

function paymentManager(): PaymentGatewayManager
{
    return app(PaymentGatewayManager::class);
}

it('resolves the driver named in config', function (string $driver, string $expected) {
    config(['payments.gateway' => $driver]);

    expect(paymentManager()->driver())->toBeInstanceOf($expected);
})->with([
    ['thawani', ThawaniGateway::class],
    ['fake', FakePaymentGateway::class],
]);

/**
 * `config->get()`'s default does not cover an explicit null, and `PAYMENT_GATEWAY=` in an
 * env file yields an empty string — either would otherwise resolve to a driver named "".
 */
it('falls back to thawani when the configured driver is absent or blank', function (mixed $configured) {
    config(['payments.gateway' => $configured]);

    expect(paymentManager()->getDefaultDriver())->toBe('thawani');
})->with(['null' => null, 'empty string' => '', 'whitespace' => '   ']);

it('resolves a named driver explicitly, whatever the default is', function () {
    config(['payments.gateway' => 'fake']);

    expect(paymentManager()->driver('thawani'))->toBeInstanceOf(ThawaniGateway::class)
        ->and(paymentManager()->driver('fake'))->toBeInstanceOf(FakePaymentGateway::class);
});

it('memoises each driver', function () {
    $manager = paymentManager();

    expect($manager->driver('fake'))->toBe($manager->driver('fake'));
});

it('throws for an unknown driver rather than falling back to a real provider', function () {
    config(['payments.gateway' => 'nonsense']);

    expect(fn () => paymentManager()->driver())->toThrow(InvalidArgumentException::class);
});

it('lets a caller register a driver through extend', function () {
    $custom = new FakePaymentGateway;

    paymentManager()->extend('custom', fn (): PaymentGateway => $custom);

    expect(paymentManager()->driver('custom'))->toBe($custom);
});

it('binds the contract to the active driver so callers never type-hint the manager', function () {
    config(['payments.gateway' => 'fake']);

    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class)
        ->and(app(PaymentGateway::class))->toBeInstanceOf(PaymentGateway::class);
});

it('shares the manager so an extended driver is visible to later resolutions', function () {
    $custom = new FakePaymentGateway;

    app(PaymentGatewayManager::class)->extend('custom', fn (): PaymentGateway => $custom);
    config(['payments.gateway' => 'custom']);

    expect(app(PaymentGateway::class))->toBe($custom);
});

/**
 * The suite must never be able to reach a real provider by accident.
 */
it('uses the fake driver by default under test', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

/**
 * Every driver is handed out only as the contract, so nothing downstream can reach for a
 * Thawani-specific method and quietly re-couple the domain to the provider.
 */
it('exposes every driver as the contract and nothing more', function (string $driver) {
    expect(paymentManager()->driver($driver))->toBeInstanceOf(PaymentGateway::class);
})->with(['thawani', 'fake']);
