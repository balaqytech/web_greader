<?php

declare(strict_types=1);

use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\CheckoutSessionDTO;
use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payments\Drivers\ThawaniGateway;
use App\Support\Money\OmrAmount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['thawani' => [
        'mode' => 'test',
        'test' => [
            'base_url' => 'https://uatcheckout.thawani.om/api/v1',
            'checkout_base_url' => 'https://uatcheckout.thawani.om/pay',
            'secret_key' => 'test_secret_key',
            'publishable_key' => 'test_publishable_key',
        ],
    ]]);
});

function thawaniGateway(): ThawaniGateway
{
    return new ThawaniGateway;
}

function checkoutRequest(string $amount = '25.000'): CheckoutRequestDTO
{
    return new CheckoutRequestDTO(
        clientReference: '01hpayment000000000000000ab',
        amount: OmrAmount::fromString($amount),
        productName: 'Registration Fee',
        successUrl: 'https://app.test/return/success',
        cancelUrl: 'https://app.test/return/cancel',
        metadata: ['application_ref' => 'APP-2026000001'],
    );
}

function fakeSession(array $overrides = []): array
{
    return ['data' => array_merge([
        'session_id' => 'sess_abc123',
        'client_reference_id' => '01hpayment000000000000000ab',
        'payment_status' => 'unpaid',
        'total_amount' => 25000,
        'currency' => 'OMR',
        'mode' => 'payment',
        'expire_at' => now()->addDay()->toIso8601String(),
    ], $overrides)];
}

it('creates a checkout session and returns a checkout url', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession())]);

    $session = thawaniGateway()->createCheckout(checkoutRequest());

    expect($session)->toBeInstanceOf(CheckoutSessionDTO::class)
        ->and($session->sessionId)->toBe('sess_abc123')
        ->and($session->checkoutUrl)->toBe('https://uatcheckout.thawani.om/pay/sess_abc123?key=test_publishable_key')
        ->and($session->expiresAt)->not->toBeNull();
});

/**
 * The single place OMR becomes baisa. An off-by-one-baisa amount reconciles to nothing.
 */
it('converts the amount to exact integer baisa in the request', function (string $amount, int $baisa) {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession())]);

    thawaniGateway()->createCheckout(checkoutRequest($amount));

    Http::assertSent(function (Request $request) use ($baisa): bool {
        return $request['products'][0]['unit_amount'] === $baisa
            && is_int($request['products'][0]['unit_amount']);
    });
})->with([
    ['25.000', 25000],
    ['0.070', 70],
    ['1.005', 1005],
    ['150.750', 150750],
    ['0.001', 1],
]);

it('sends the client reference so a session can be found again without its id', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession())]);

    thawaniGateway()->createCheckout(checkoutRequest());

    Http::assertSent(fn (Request $request): bool => $request['client_reference_id'] === '01hpayment000000000000000ab');
});

it('authenticates with the configured secret key', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession())]);

    thawaniGateway()->createCheckout(checkoutRequest());

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('thawani-api-key', 'test_secret_key'));
});

it('refuses to transact when the configuration is unusable', function () {
    config(['thawani.test.secret_key' => null]);
    Http::fake();

    expect(fn () => thawaniGateway()->createCheckout(checkoutRequest()))
        ->toThrow(PaymentGatewayException::class);

    Http::assertNothingSent();
});

it('refuses to transact with the vendor package\'s bundled key', function () {
    config(['thawani.test.secret_key' => 'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et']);
    Http::fake();

    expect(fn () => thawaniGateway()->createCheckout(checkoutRequest()))
        ->toThrow(PaymentGatewayException::class);

    Http::assertNothingSent();
});

it('fails when the create response carries no session id', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['data' => ['payment_status' => 'unpaid']])]);

    expect(fn () => thawaniGateway()->createCheckout(checkoutRequest()))
        ->toThrow(PaymentGatewayException::class, 'no session_id');
});

it('fails when the response has no data object', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['success' => true])]);

    expect(fn () => thawaniGateway()->createCheckout(checkoutRequest()))
        ->toThrow(PaymentGatewayException::class, 'no data object');
});

it('retrieves a session and maps its status', function (string $providerStatus, ProviderPaymentOutcome $expected) {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession(['payment_status' => $providerStatus]))]);

    expect(thawaniGateway()->retrieveSession('sess_abc123')->outcome)->toBe($expected);
})->with([
    ['paid', ProviderPaymentOutcome::PAID],
    ['unpaid', ProviderPaymentOutcome::UNPAID],
    ['cancelled', ProviderPaymentOutcome::CANCELLED],
    ['canceled', ProviderPaymentOutcome::CANCELLED],
    ['failed', ProviderPaymentOutcome::FAILED],
    ['declined', ProviderPaymentOutcome::FAILED],
    ['expired', ProviderPaymentOutcome::EXPIRED],
    ['PAID', ProviderPaymentOutcome::PAID],
]);

/**
 * Guessing an unmapped status into PAID would invent money; guessing it into FAILED would
 * abandon it. UNKNOWN keeps the attempt pending so the provider is asked again.
 */
it('maps an unrecognised or missing status to UNKNOWN rather than guessing', function (mixed $providerStatus) {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession(['payment_status' => $providerStatus]))]);

    $status = thawaniGateway()->retrieveSession('sess_abc123');

    expect($status->outcome)->toBe(ProviderPaymentOutcome::UNKNOWN)
        ->and($status->outcome->isTerminal())->toBeFalse();
})->with(['something_new' => 'something_new', 'null' => null, 'numeric' => 42, 'empty' => '']);

/**
 * A lapsed-but-unpaid session can never become paid, so it is reported as expired rather
 * than left looking merely open.
 */
it('reports an unpaid session whose window has closed as expired', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession([
        'payment_status' => 'unpaid',
        'expire_at' => now()->subHour()->toIso8601String(),
    ]))]);

    expect(thawaniGateway()->retrieveSession('sess_abc123')->outcome)->toBe(ProviderPaymentOutcome::EXPIRED);
});

it('does not expire a session that is paid but past its window', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession([
        'payment_status' => 'paid',
        'expire_at' => now()->subHour()->toIso8601String(),
    ]))]);

    expect(thawaniGateway()->retrieveSession('sess_abc123')->outcome)->toBe(ProviderPaymentOutcome::PAID);
});

it('tolerates an unparseable or missing expiry without failing the read', function (mixed $expiry) {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession(['expire_at' => $expiry]))]);

    expect(thawaniGateway()->retrieveSession('sess_abc123')->outcome)->toBe(ProviderPaymentOutcome::UNPAID);
})->with(['garbage' => 'not-a-date', 'null' => null, 'array' => [[]]]);

it('converts the provider total from baisa back to exact OMR', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession(['total_amount' => 1005]))]);

    $status = thawaniGateway()->retrieveSession('sess_abc123');

    expect($status->amount)->toBeInstanceOf(OmrAmount::class)
        ->and($status->amount->value)->toBe('1.005');
});

it('drops a nonsensical provider total rather than mangling it', function (mixed $total) {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession(['total_amount' => $total]))]);

    expect(thawaniGateway()->retrieveSession('sess_abc123')->amount)->toBeNull();
})->with(['negative' => -5, 'text' => 'abc', 'null' => null, 'float' => 1.5]);

/**
 * The payload is persisted onto the payment row and surfaces in support and audit views, so
 * it is an allowlist: a denylist starts leaking the day the provider adds a field.
 */
it('sanitises the stored payload to an allowlist', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession([
        'secret_key' => 'leaked_secret',
        'api_key' => 'leaked_api_key',
        'customer_token' => 'leaked_token',
        'something_new' => 'unexpected',
    ]))]);

    $payload = thawaniGateway()->retrieveSession('sess_abc123')->payload;

    expect($payload)->toHaveKey('session_id')
        ->and($payload)->toHaveKey('payment_status')
        ->and($payload)->not->toHaveKey('secret_key')
        ->and($payload)->not->toHaveKey('api_key')
        ->and($payload)->not->toHaveKey('customer_token')
        ->and($payload)->not->toHaveKey('something_new')
        ->and(json_encode($payload))->not->toContain('leaked');
});

it('keeps only scalar values in the payload', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession([
        'invoice' => ['nested' => ['deep' => 'value']],
    ]))]);

    $payload = thawaniGateway()->retrieveSession('sess_abc123')->payload;

    foreach ($payload as $value) {
        expect($value === null || is_scalar($value))->toBeTrue();
    }
});

/**
 * Money-safety asymmetry, half one: creating a session captures no money and the guardian is
 * never redirected, so a provider error may safely be final.
 */
it('treats a provider error during create as final', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['description' => 'invalid amount', 'code' => 4001], 400)]);

    try {
        thawaniGateway()->createCheckout(checkoutRequest());
    } catch (PaymentGatewayException $e) {
        expect($e->retryable)->toBeFalse();

        return;
    }

    $this->fail('Expected a PaymentGatewayException.');
});

/**
 * Money-safety asymmetry, half two: retrieval asks whether money moved. Concluding "failed"
 * from an error we cannot read would abandon a payment the guardian may have made.
 */
it('treats a provider error during retrieval as retryable, never as a failed payment', function (int $status) {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['description' => 'boom'], $status)]);

    try {
        thawaniGateway()->retrieveSession('sess_abc123');
    } catch (PaymentGatewayException $e) {
        expect($e->retryable)->toBeTrue();

        return;
    }

    $this->fail('Expected a PaymentGatewayException.');
})->with([400, 404, 500, 503]);

it('treats an unreachable provider as retryable on every operation', function (string $operation) {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    try {
        match ($operation) {
            'create' => thawaniGateway()->createCheckout(checkoutRequest()),
            'retrieve' => thawaniGateway()->retrieveSession('sess_abc123'),
            'reference' => thawaniGateway()->retrieveByClientReference('01hpayment000000000000000ab'),
            'cancel' => thawaniGateway()->cancelSession('sess_abc123'),
        };
    } catch (PaymentGatewayException $e) {
        expect($e->retryable)->toBeTrue()
            ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class);

        return;
    }

    $this->fail('Expected a PaymentGatewayException.');
})->with(['create', 'retrieve', 'reference', 'cancel']);

it('retrieves a session by its client reference', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession(['payment_status' => 'paid']))]);

    $status = thawaniGateway()->retrieveByClientReference('01hpayment000000000000000ab');

    expect($status)->toBeInstanceOf(ProviderSessionStatusDTO::class)
        ->and($status->outcome)->toBe(ProviderPaymentOutcome::PAID)
        ->and($status->clientReference)->toBe('01hpayment000000000000000ab');
});

it('unwraps a list response when retrieving by client reference', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['data' => [[
        'session_id' => 'sess_abc123',
        'payment_status' => 'paid',
    ]]])]);

    expect(thawaniGateway()->retrieveByClientReference('ref')?->outcome)->toBe(ProviderPaymentOutcome::PAID);
});

it('returns null when the provider knows of no session for the reference', function (array $data) {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['data' => $data])]);

    expect(thawaniGateway()->retrieveByClientReference('unknown-ref'))->toBeNull();
})->with(['empty object' => [[]], 'empty list' => [[[]]]]);

it('cancels an open session', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['success' => true, 'data' => []])]);

    expect(thawaniGateway()->cancelSession('sess_abc123'))->toBeTrue();
});

/**
 * A provider refusing to cancel — almost always because the session already settled — is
 * answering, not failing, and must not take down the caller.
 */
it('reports a refused cancellation as false rather than throwing', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(['description' => 'session already paid'], 400)]);

    expect(thawaniGateway()->cancelSession('sess_abc123'))->toBeFalse();
});

/**
 * The boundary that makes the provider replaceable: nothing from the vendor package may
 * appear in what the adapter hands back.
 */
it('never leaks a vendor package type into the domain', function () {
    Http::fake(['uatcheckout.thawani.om/*' => Http::response(fakeSession(['payment_status' => 'paid']))]);

    $gateway = thawaniGateway();

    $returned = [
        $gateway->createCheckout(checkoutRequest()),
        $gateway->retrieveSession('sess_abc123'),
        $gateway->retrieveByClientReference('01hpayment000000000000000ab'),
    ];

    foreach ($returned as $value) {
        expect($value::class)->toStartWith('App\\DTOs\\Payments\\')
            ->and($value::class)->not->toStartWith('Jkbroot\\');
    }
});
