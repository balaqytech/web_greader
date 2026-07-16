<?php

declare(strict_types=1);

use App\DTOs\Payments\CheckoutRequestDTO;
use App\Enums\ProviderPaymentOutcome;
use App\Services\Payments\Drivers\ThawaniGateway;
use App\Support\Money\OmrAmount;
use Illuminate\Support\Str;

/**
 * Opt-in probe against Thawani's real UAT environment: creates a checkout session, retrieves
 * it, retrieves it again by client reference, and cancels it.
 *
 * Everything else in the payment suite runs against `Http::fake()` or the fake driver, which
 * proves the adapter handles the responses we *believe* Thawani sends. Only this test can
 * show that belief matches reality — that the request shape is accepted, and that the
 * response really does carry `session_id`, `payment_status` and `expire_at` where the
 * adapter looks for them.
 *
 * Safety, by design:
 * - **Never runs against live.** `THAWANI_MODE` must be `test`; anything else fails loudly.
 *   Against live, this would create real checkout sessions on the real merchant account.
 * - Opt-in parsing is strict, mirroring RealEngineRowLockTest: unset/empty and recognised
 *   false values skip; recognised true values enable; anything else throws before a single
 *   byte is sent — a typo must not masquerade as "opted out".
 * - Once opted in, missing credentials fail loudly rather than skipping, so an opt-in that
 *   silently did nothing is impossible.
 * - `THAWANI_UAT_TEST_ENABLED` is not set in `.env`, `.env.example`, or `phpunit.xml`, so a
 *   normal run never attempts a network call.
 * - Tagged `integration` for explicit selection/exclusion.
 * - Only ever creates a session and cancels it again. It never captures money — completing a
 *   payment needs a human on Thawani's hosted page — so nothing here can settle a fee.
 *
 * To opt in, put real UAT credentials in your environment (never commit them) and run:
 *   THAWANI_UAT_TEST_ENABLED=true THAWANI_MODE=test \
 *   THAWANI_TEST_SECRET_KEY=... THAWANI_TEST_PUBLISHABLE_KEY=... \
 *   vendor/bin/pest --group=integration --filter=ThawaniUat
 *
 * NOTE: this test has never been executed against real UAT credentials — none were available
 * in the environment where it was written. Its skip and fail-loud paths are verified; the
 * happy path is not. Do not treat it as evidence the UAT integration works until it has
 * actually been run.
 */
function parseThawaniUatOptIn(mixed $raw): bool
{
    if ($raw === null || $raw === '') {
        return false;
    }

    if (is_bool($raw)) {
        return $raw;
    }

    $normalized = strtolower(trim((string) $raw));

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    throw new RuntimeException(
        'THAWANI_UAT_TEST_ENABLED=['.$raw.'] is not a recognized boolean value. Use one of: '
        .'true, false, 1, 0, yes, no, on, off (case-insensitive), or leave it unset to '
        .'disable this opt-in integration test.'
    );
}

function thawaniUatOptedIn(): bool
{
    return parseThawaniUatOptIn(env('THAWANI_UAT_TEST_ENABLED'));
}

/**
 * Only called once opted in — misconfiguration at that point must fail loudly, not skip.
 */
function assertThawaniUatConfigured(): void
{
    $mode = env('THAWANI_MODE');

    if ($mode !== 'test') {
        throw new RuntimeException(
            'THAWANI_UAT_TEST_ENABLED=true requires THAWANI_MODE=test, got ['.var_export($mode, true).']. '
            .'This test creates real checkout sessions and must never run against the live merchant account.'
        );
    }

    $missing = array_keys(array_filter([
        'THAWANI_TEST_SECRET_KEY' => blank(env('THAWANI_TEST_SECRET_KEY')),
        'THAWANI_TEST_PUBLISHABLE_KEY' => blank(env('THAWANI_TEST_PUBLISHABLE_KEY')),
    ]));

    if ($missing !== []) {
        throw new RuntimeException(
            'THAWANI_UAT_TEST_ENABLED=true but required variable(s) are missing: '.implode(', ', $missing).'. '
            .'Opting in requires real UAT credentials to be set explicitly.'
        );
    }
}

it('creates, retrieves and cancels a real UAT checkout session', function () {
    if (! thawaniUatOptedIn()) {
        $this->markTestSkipped('Opt-in: set THAWANI_UAT_TEST_ENABLED=true with real UAT credentials to run.');
    }

    assertThawaniUatConfigured();

    $gateway = new ThawaniGateway;
    $reference = 'uat-probe-'.Str::lower(Str::ulid());

    $session = $gateway->createCheckout(new CheckoutRequestDTO(
        clientReference: $reference,
        amount: OmrAmount::fromString('0.100'),
        productName: 'UAT probe — not a real fee',
        successUrl: 'https://example.test/success',
        cancelUrl: 'https://example.test/cancel',
        metadata: ['probe' => 'true'],
    ));

    expect($session->sessionId)->not->toBeEmpty()
        ->and($session->checkoutUrl)->toStartWith('https://uatcheckout.thawani.om/pay/');

    // The response really does carry payment_status where the adapter looks for it, and a
    // freshly created session really is unpaid — the belief every faked test rests on.
    $status = $gateway->retrieveSession($session->sessionId);

    expect($status->sessionId)->toBe($session->sessionId)
        ->and($status->outcome)->toBe(ProviderPaymentOutcome::UNPAID)
        ->and($status->outcome)->not->toBe(ProviderPaymentOutcome::UNKNOWN);

    // The recovery path used by reconciliation when a session id was never learned.
    $byReference = $gateway->retrieveByClientReference($reference);

    expect($byReference?->sessionId)->toBe($session->sessionId);

    expect($gateway->cancelSession($session->sessionId))->toBeTrue();

    expect($gateway->retrieveSession($session->sessionId)->outcome)
        ->toBe(ProviderPaymentOutcome::CANCELLED);
})->group('integration');

it('parses the opt-in flag strictly', function (mixed $raw, bool $expected) {
    expect(parseThawaniUatOptIn($raw))->toBe($expected);
})->with([
    'unset' => [null, false],
    'empty' => ['', false],
    'false string' => ['false', false],
    'zero' => ['0', false],
    'off' => ['off', false],
    'real false' => [false, false],
    'true string' => ['true', true],
    'one' => ['1', true],
    'yes' => ['YES', true],
    'real true' => [true, true],
]);

/**
 * A typo must not masquerade as "opted out" — that would silently disable the only test that
 * checks our assumptions against the real provider.
 */
it('throws on an unrecognised opt-in value rather than silently disabling itself', function (string $raw) {
    expect(fn () => parseThawaniUatOptIn($raw))->toThrow(RuntimeException::class);
})->with(['ture', 'enabled', 'maybe', '2']);
