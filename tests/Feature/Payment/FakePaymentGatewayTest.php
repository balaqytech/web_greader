<?php

declare(strict_types=1);

use App\DTOs\Payments\CheckoutRequestDTO;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Support\Money\OmrAmount;
use Illuminate\Support\Facades\Http;

function fakeGateway(): FakePaymentGateway
{
    return new FakePaymentGateway;
}

function fakeCheckoutRequest(string $reference = 'ref-1', string $amount = '25.000'): CheckoutRequestDTO
{
    return new CheckoutRequestDTO(
        clientReference: $reference,
        amount: OmrAmount::fromString($amount),
        productName: 'Registration Fee',
        successUrl: 'https://app.test/success',
        cancelUrl: 'https://app.test/cancel',
    );
}

it('opens an unpaid session', function () {
    $session = fakeGateway()->createCheckout(fakeCheckoutRequest());

    expect($session->sessionId)->not->toBeEmpty()
        ->and($session->checkoutUrl)->toContain($session->sessionId)
        ->and($session->expiresAt)->not->toBeNull();
});

it('issues a distinct session per attempt', function () {
    $gateway = fakeGateway();

    expect($gateway->createCheckout(fakeCheckoutRequest('a'))->sessionId)
        ->not->toBe($gateway->createCheckout(fakeCheckoutRequest('b'))->sessionId);
});

it('starts every session unpaid, so nothing is settled without a retrieval', function () {
    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest());

    expect($gateway->retrieveSession($session->sessionId)->outcome)->toBe(ProviderPaymentOutcome::UNPAID);
});

/**
 * The domain only ever learns an outcome by asking, exactly as in production — a redirect
 * cannot declare success.
 */
it('reflects an out-of-band settlement only through a retrieval', function (ProviderPaymentOutcome $outcome) {
    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest());

    $gateway->settle($session->sessionId, $outcome);

    expect($gateway->retrieveSession($session->sessionId)->outcome)->toBe($outcome);
})->with([
    ProviderPaymentOutcome::PAID,
    ProviderPaymentOutcome::FAILED,
    ProviderPaymentOutcome::EXPIRED,
    ProviderPaymentOutcome::CANCELLED,
]);

it('preserves the amount across a settlement', function () {
    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest('ref-1', '1.005'));

    $gateway->settle($session->sessionId, ProviderPaymentOutcome::PAID);

    expect($gateway->retrieveSession($session->sessionId)->amount?->value)->toBe('1.005');
});

it('can settle with a different amount than was requested, to model a mismatch', function () {
    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest('ref-1', '25.000'));

    $gateway->settle($session->sessionId, ProviderPaymentOutcome::PAID, OmrAmount::fromString('5.000'));

    expect($gateway->retrieveSession($session->sessionId)->amount?->value)->toBe('5.000');
});

it('throws for a session it never issued', function () {
    expect(fn () => fakeGateway()->retrieveSession('nope'))
        ->toThrow(PaymentGatewayException::class);
});

it('finds a session by client reference', function () {
    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest('my-ref'));

    expect($gateway->retrieveByClientReference('my-ref')?->sessionId)->toBe($session->sessionId);
});

it('returns null for an unknown client reference', function () {
    expect(fakeGateway()->retrieveByClientReference('unknown'))->toBeNull();
});

it('cancels an open session', function () {
    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest());

    expect($gateway->cancelSession($session->sessionId))->toBeTrue()
        ->and($gateway->retrieveSession($session->sessionId)->outcome)->toBe(ProviderPaymentOutcome::CANCELLED);
});

/**
 * Mirrors the real provider: an already-settled session cannot be cancelled, and saying so
 * is an answer rather than an error.
 */
it('refuses to cancel a settled session without throwing', function () {
    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest());
    $gateway->settle($session->sessionId, ProviderPaymentOutcome::PAID);

    expect($gateway->cancelSession($session->sessionId))->toBeFalse()
        ->and($gateway->retrieveSession($session->sessionId)->outcome)->toBe(ProviderPaymentOutcome::PAID);
});

it('refuses to cancel an unknown session without throwing', function () {
    expect(fakeGateway()->cancelSession('nope'))->toBeFalse();
});

it('can simulate a retryable outage so callers can be tested against one', function () {
    $gateway = fakeGateway();
    $gateway->willFailWith(PaymentGatewayException::unreachable(new RuntimeException('timeout')));

    try {
        $gateway->createCheckout(fakeCheckoutRequest());
    } catch (PaymentGatewayException $e) {
        expect($e->retryable)->toBeTrue();

        $gateway->stopFailing();

        expect($gateway->createCheckout(fakeCheckoutRequest())->sessionId)->not->toBeEmpty();

        return;
    }

    $this->fail('Expected a PaymentGatewayException.');
});

it('can simulate a final rejection', function () {
    $gateway = fakeGateway();
    $gateway->willFailWith(PaymentGatewayException::rejected('invalid amount'));

    try {
        $gateway->createCheckout(fakeCheckoutRequest());
    } catch (PaymentGatewayException $e) {
        expect($e->retryable)->toBeFalse();

        return;
    }

    $this->fail('Expected a PaymentGatewayException.');
});

it('never touches the network', function () {
    Http::fake();

    $gateway = fakeGateway();
    $session = $gateway->createCheckout(fakeCheckoutRequest());
    $gateway->settle($session->sessionId, ProviderPaymentOutcome::PAID);
    $gateway->retrieveSession($session->sessionId);
    $gateway->retrieveByClientReference('ref-1');
    $gateway->cancelSession($session->sessionId);

    Http::assertNothingSent();
});
