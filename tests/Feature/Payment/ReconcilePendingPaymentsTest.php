<?php

declare(strict_types=1);

use App\Actions\Payments\InitiatePaymentAction;
use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Enums\PaymentMethod;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Models\Application;
use App\Models\Payment;
use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\Support\Money\OmrAmount;
use App\Support\Settings\PaymentSettings;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    app(PaymentSettings::class)
        ->setRegistrationFee(OmrAmount::fromString('25.000'));

    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
});

function quietPayment(): Payment
{
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $payment = app(InitiatePaymentAction::class)->execute(
        new InitiatePaymentDTO($application, PaymentMethod::THAWANI)
    );

    // Gone quiet: past the staleness window.
    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['updated_at' => now()->subHour()]);

    return $payment->fresh();
}

/**
 * The scenario the whole command exists for: without a webhook, a guardian who pays and then
 * closes the tab never triggers a return, and would otherwise sit unpaid with money taken.
 */
it('settles a payment the guardian completed but never returned from', function () {
    $payment = quietPayment();
    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::PAID);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf(Paid::class)
        ->and($payment->fresh()->application->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

it('maps a declined and an expired session', function (ProviderPaymentOutcome $outcome, string $expected) {
    $payment = quietPayment();
    $this->gateway->settle($payment->provider_session_id, $outcome);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf($expected);
})->with([
    [ProviderPaymentOutcome::FAILED, Failed::class],
    [ProviderPaymentOutcome::EXPIRED, Expired::class],
]);

it('leaves a session the provider still reports as open alone', function () {
    $payment = quietPayment();

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

/**
 * A checkout the guardian is on right now would just come back UNPAID and waste a provider
 * call.
 */
it('ignores attempts that have not gone quiet yet', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = app(InitiatePaymentAction::class)->execute(
        new InitiatePaymentDTO($application, PaymentMethod::THAWANI)
    );

    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::PAID);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

it('honours a stale-minutes override', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = app(InitiatePaymentAction::class)->execute(
        new InitiatePaymentDTO($application, PaymentMethod::THAWANI)
    );
    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::PAID);

    $this->artisan('payments:reconcile', ['--stale-minutes' => 0])->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf(Paid::class);
});

it('never touches terminal attempts', function (string $factoryState) {
    $payment = Payment::factory()->thawani()->{$factoryState}()->create();
    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['updated_at' => now()->subHour()]);

    $before = $payment->fresh()->status::$name;

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status::$name)->toBe($before);
})->with(['paid', 'failed', 'expired']);

it('never touches bank transfer or cash attempts', function (string $factoryState) {
    $payment = Payment::factory()->{$factoryState}()->pending()->create();
    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['updated_at' => now()->subHour()]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
})->with(['bankTransfer', 'cash']);

/**
 * The recovery path for an attempt whose initiation timed out after the provider had already
 * created the session: no session id was ever stored, so it can only be found by reference.
 */
it('recovers an attempt that never learned its session id, by client reference', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    // Initiation reached the provider but the response never came back.
    $this->gateway->willFailWith(PaymentGatewayException::unreachable(new RuntimeException('timeout')));

    try {
        app(InitiatePaymentAction::class)->execute(
            new InitiatePaymentDTO($application, PaymentMethod::THAWANI)
        );
    } catch (PaymentGatewayException) {
        // Expected.
    }

    $payment = Payment::query()->where('application_id', $application->id)->firstOrFail();
    expect($payment->provider_session_id)->toBeNull();

    // The session did exist at the provider all along, and the guardian paid it.
    $this->gateway->stopFailing();
    $session = $this->gateway->createCheckout(new CheckoutRequestDTO(
        clientReference: $payment->reference,
        amount: $payment->money(),
        productName: 'Registration Fee',
        successUrl: 'https://app.test/s',
        cancelUrl: 'https://app.test/c',
    ));
    $this->gateway->settle($session->sessionId, ProviderPaymentOutcome::PAID);

    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['updated_at' => now()->subHour()]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf(Paid::class);
});

/**
 * One unreachable provider must not abort the run — the next attempt may genuinely need
 * settling, and a partial pass beats none.
 */
it('keeps going when the provider cannot be reached, concluding nothing', function () {
    $payment = quietPayment();
    $this->gateway->willFailWith(PaymentGatewayException::unreachable(new RuntimeException('timeout')));

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

it('reports nothing to do when no attempt is due', function () {
    $this->artisan('payments:reconcile')
        ->expectsOutputToContain('No pending payments are due')
        ->assertSuccessful();
});

it('respects the limit option', function () {
    $payments = collect(range(1, 3))->map(fn () => quietPayment());
    $payments->each(fn (Payment $p) => $this->gateway->settle($p->provider_session_id, ProviderPaymentOutcome::PAID));

    $this->artisan('payments:reconcile', ['--limit' => 1])->assertSuccessful();

    expect(Payment::query()->paid()->count())->toBe(1);
});

it('is scheduled, because without it payments made after the guardian left never settle', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'payments:reconcile'));

    expect($events)->toHaveCount(1);
});
