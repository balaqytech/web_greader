<?php

declare(strict_types=1);

use App\Actions\Payments\InitiatePaymentAction;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Enums\PaymentMethod;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\Payment;
use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\Support\Money\OmrAmount;
use App\Support\Settings\PaymentSettings;

beforeEach(function () {
    app(PaymentSettings::class)
        ->setRegistrationFee(OmrAmount::fromString('25.000'));

    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
});

function startedPayment(): Payment
{
    $application = Application::factory()->awaitingRegistrationFee()->create();

    return app(InitiatePaymentAction::class)->execute(
        new InitiatePaymentDTO($application, PaymentMethod::THAWANI)
    );
}

function visitReturn(Payment $payment, string $outcome = 'success')
{
    return test()->get(route('payments.return', ['payment' => $payment->reference, 'outcome' => $outcome]));
}

/**
 * THE rule. The redirect is client-controlled — a guardian can type `?outcome=success` into
 * the URL bar — so it must gain them nothing. The provider is the only authority.
 */
it('does not settle a payment just because the redirect claims success', function () {
    $payment = startedPayment();

    visitReturn($payment, 'success')->assertOk();

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class)
        ->and($payment->fresh()->application->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('settles only when the provider itself confirms payment', function () {
    $payment = startedPayment();
    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::PAID);

    visitReturn($payment)->assertOk();

    expect($payment->fresh()->status)->toBeInstanceOf(Paid::class)
        ->and($payment->fresh()->application->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

/**
 * The inverse: a "cancel" redirect must not fail a payment the provider says was paid.
 */
it('settles a genuinely paid session even when the redirect says cancel', function () {
    $payment = startedPayment();
    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::PAID);

    visitReturn($payment, 'cancel')->assertOk();

    expect($payment->fresh()->status)->toBeInstanceOf(Paid::class);
});

it('maps a provider decline, cancellation and expiry', function (ProviderPaymentOutcome $outcome, string $expected) {
    $payment = startedPayment();
    $this->gateway->settle($payment->provider_session_id, $outcome);

    visitReturn($payment)->assertOk();

    expect($payment->fresh()->status)->toBeInstanceOf($expected)
        ->and($payment->fresh()->application->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
})->with([
    [ProviderPaymentOutcome::FAILED, Failed::class],
    [ProviderPaymentOutcome::CANCELLED, Failed::class],
    [ProviderPaymentOutcome::EXPIRED, Expired::class],
]);

/**
 * Replays must be safe: guardians refresh return pages.
 */
it('is idempotent across repeated returns', function () {
    $payment = startedPayment();
    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::PAID);

    visitReturn($payment)->assertOk();
    visitReturn($payment)->assertOk();
    visitReturn($payment)->assertOk();

    expect(Payment::query()->paid()->count())->toBe(1)
        ->and(ApplicationActivity::where('application_id', $payment->application_id)
            ->where('to_state', AwaitingApplicationCompletion::$name)->count())->toBe(1);
});

it('does not re-ask the provider about an already settled attempt', function () {
    $payment = startedPayment();
    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::PAID);

    visitReturn($payment)->assertOk();

    // The provider now reports something different; a settled attempt must not be revisited.
    $this->gateway->settle($payment->provider_session_id, ProviderPaymentOutcome::FAILED);

    visitReturn($payment)->assertOk();

    expect($payment->fresh()->status)->toBeInstanceOf(Paid::class);
});

/**
 * Not knowing is not the same as knowing it failed.
 */
it('concludes nothing when the provider cannot be reached', function () {
    $payment = startedPayment();
    $this->gateway->willFailWith(PaymentGatewayException::unreachable(new RuntimeException('timeout')));

    visitReturn($payment)->assertOk();

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

it('404s an unknown payment reference rather than revealing anything', function () {
    $this->get(route('payments.return', ['payment' => 'nope', 'outcome' => 'success']))
        ->assertNotFound();
});

it('rejects an outcome segment that is not a known value', function () {
    $payment = startedPayment();

    $this->get(url("/payments/{$payment->reference}/return/anything-else"))->assertNotFound();
});

/**
 * The page is public and unauthenticated, so it must disclose only this payment's own
 * status — never the application's data or the guardian's details.
 */
it('discloses only the payment reference and status, no application or guardian data', function () {
    $payment = startedPayment();
    $application = $payment->application;

    $response = visitReturn($payment);

    $response->assertOk()
        ->assertSee($payment->reference)
        ->assertDontSee($application->student_name)
        ->assertDontSee((string) $application->ref_no)
        ->assertDontSee($application->father_phone ?? 'no-phone')
        ->assertDontSee($payment->provider_session_id);
});

it('is reachable without authentication, since the guardian arrives from a hosted page', function () {
    $payment = startedPayment();

    expect(auth()->check())->toBeFalse();

    visitReturn($payment)->assertOk();
});
