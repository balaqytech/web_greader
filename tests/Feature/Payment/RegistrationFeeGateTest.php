<?php

declare(strict_types=1);

use App\Enums\PaymentPurpose;
use App\Exceptions\UnpaidRegistrationFeeException;
use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\Payment;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use Illuminate\Support\Facades\Log;

/**
 * The registration-fee gate: the single rule that an application only advances when its fee
 * has actually been paid.
 */
it('advances an application when its fee payment is settled', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $settled = $payment->status->transitionTo(Paid::class);

    expect($settled->status)->toBeInstanceOf(Paid::class)
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

it('records the fee settlement as application activity', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $payment->status->transitionTo(Paid::class);

    $activity = ApplicationActivity::where('application_id', $application->id)
        ->where('to_state', AwaitingApplicationCompletion::$name)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->from_state)->toBe(AwaitingRegistrationFee::$name)
        ->and($activity->notes)->toContain($payment->reference);
});

/**
 * The core enforcement: there is no way to express "advance past the fee gate, trust me".
 * This is exactly what the removed SubmitApplicationFilamentAction did, gated only by
 * `Update:Application` — a permission every branch staffer holds.
 */
it('cannot advance an application without supplying a payment at all', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect(fn () => $application->status->transitionTo(AwaitingApplicationCompletion::class))
        ->toThrow(UnpaidRegistrationFeeException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

/**
 * Spatie instantiates the transition with the model alone to answer canTransitionTo(), so a
 * required payment parameter would make every Filament visibility check a fatal
 * ArgumentCountError. Pinned so the parameter is never "tidied" back to required.
 */
it('answers canTransitionTo without a payment instead of fataling', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect($application->status->canTransitionTo(AwaitingApplicationCompletion::class))->toBeTrue();
});

it('refuses to advance on an unpaid payment', function (string $factoryState) {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->{$factoryState}()->create();

    expect(fn () => $application->status->transitionTo(AwaitingApplicationCompletion::class, $payment))
        ->toThrow(UnpaidRegistrationFeeException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
})->with(['pending', 'failed', 'expired', 'rejected', 'awaitingVerification']);

/**
 * A paid payment is not a bearer token for any application.
 */
it('refuses a paid payment that belongs to a different application', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $other = Application::factory()->awaitingRegistrationFee()->create();
    $othersPayment = Payment::factory()->forApplication($other)->paid()->create();

    expect(fn () => $application->status->transitionTo(AwaitingApplicationCompletion::class, $othersPayment))
        ->toThrow(UnpaidRegistrationFeeException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('refuses a paid payment that is not for the registration fee', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->paid()->create();

    // Written directly: PaymentPurpose has only one case today, so the guard is proven by
    // forcing an unknown purpose rather than by a second enum case that does not exist yet.
    //
    // The refusal lands one layer earlier than the transition's own purpose guard: the enum
    // cast will not hydrate a tampered row at all. That is the stronger outcome — a purpose
    // the domain does not recognise cannot even be loaded, let alone settle a fee — so it is
    // pinned here rather than worked around to reach the guard behind it.
    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['purpose' => 'tuition']);

    expect(fn () => $application->status->transitionTo(
        AwaitingApplicationCompletion::class,
        Payment::withoutGlobalScopes()->findOrFail($payment->id),
    ))->toThrow(ValueError::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

/**
 * A caller holding a stale in-memory payment must not be able to replay a settlement.
 */
it('re-reads the payment under the lock rather than trusting the caller\'s copy', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->paid()->create();

    // The attempt is failed behind the caller's back after they loaded it as paid.
    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['status' => Failed::$name]);

    expect(fn () => $application->status->transitionTo(AwaitingApplicationCompletion::class, $payment))
        ->toThrow(UnpaidRegistrationFeeException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('settles the payment and advances the application atomically', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $payment->status->transitionTo(Paid::class);

    // Never a paid fee sitting against an application still waiting for it.
    expect($payment->fresh()->isPaid())->toBeTrue()
        ->and($application->fresh()->status)->not->toBeInstanceOf(AwaitingRegistrationFee::class);
});

/**
 * Legacy applications were grandfathered past the fee without a payment record. A fee
 * settled against one must not drag it backwards or advance it twice.
 */
it('settles a payment without touching an application already past the gate', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $settled = $payment->status->transitionTo(Paid::class);

    expect($settled->status)->toBeInstanceOf(Paid::class)
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

/**
 * Should be impossible — one active attempt per application is enforced by the application
 * lock — so reaching this means real money may have been taken twice.
 */
it('refuses a second successful charge, failing the loser instead of advancing twice', function () {
    Log::shouldReceive('error')->once();

    $application = Application::factory()->awaitingRegistrationFee()->create();
    $winner = Payment::factory()->forApplication($application)->pending()->create();
    $loser = Payment::factory()->forApplication($application)->pending()->create();

    $winner->status->transitionTo(Paid::class);
    expect($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);

    $result = $loser->status->transitionTo(Paid::class);

    expect($result->status)->toBeInstanceOf(Failed::class)
        ->and($result->failure_reason)->toContain($winner->reference)
        ->and(Payment::query()->where('application_id', $application->id)->paid()->count())->toBe(1);
});

it('preserves the losing attempt\'s provider evidence for reconciliation', function () {
    Log::shouldReceive('error')->once();

    $application = Application::factory()->awaitingRegistrationFee()->create();
    $winner = Payment::factory()->forApplication($application)->pending()->create();
    $loser = Payment::factory()->forApplication($application)->pending()->create();

    $winner->status->transitionTo(Paid::class);

    $evidence = ['session_id' => 'sess_double', 'payment_status' => 'paid'];
    $result = $loser->status->transitionTo(Paid::class, $evidence);

    expect($result->fresh()->provider_payload)->toBe($evidence)
        ->and($result->fresh()->status)->toBeInstanceOf(Failed::class);
});

it('does not advance the application a second time on a double charge', function () {
    Log::shouldReceive('error')->once();

    $application = Application::factory()->awaitingRegistrationFee()->create();
    $winner = Payment::factory()->forApplication($application)->pending()->create();
    $loser = Payment::factory()->forApplication($application)->pending()->create();

    $winner->status->transitionTo(Paid::class);
    $loser->status->transitionTo(Paid::class);

    expect(ApplicationActivity::where('application_id', $application->id)
        ->where('to_state', AwaitingApplicationCompletion::$name)
        ->count())->toBe(1);
});

it('fails a pending attempt with a reason, leaving the application at the gate', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $failed = $payment->status->transitionTo(Failed::class, 'card declined');

    expect($failed->status)->toBeInstanceOf(Failed::class)
        ->and($failed->failure_reason)->toBe('card declined')
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('expires a pending attempt, leaving the application at the gate', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $expired = $payment->status->transitionTo(Expired::class);

    expect($expired->status)->toBeInstanceOf(Expired::class)
        ->and($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('permits a fresh attempt after a failed one, and that attempt can settle the fee', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    Payment::factory()->forApplication($application)->pending()->create()
        ->status->transitionTo(Failed::class, 'declined');

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);

    $retry = Payment::factory()->forApplication($application)->pending()->create();
    $retry->status->transitionTo(Paid::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and(Payment::query()->where('application_id', $application->id)->count())->toBe(2);
});

it('still refuses every transition out of a terminal payment state', function (string $factoryState) {
    $payment = Payment::factory()->{$factoryState}()->create();

    foreach ([Paid::class, Failed::class, Pending::class, Expired::class] as $target) {
        expect($payment->status->canTransitionTo($target))->toBeFalse();
    }
})->with(['paid', 'failed', 'rejected', 'expired']);

it('registers the fee gate as the only edge out of AwaitingRegistrationFee besides cancellation', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect($application->status->canTransitionTo(AwaitingApplicationCompletion::class))->toBeTrue()
        ->and($application->status->canTransitionTo(AwaitingContractSignature::class))->toBeFalse()
        ->and($application->status->canTransitionTo(Accepted::class))->toBeFalse();
});

it('keeps the registration-fee purpose on settled payments', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $payment->status->transitionTo(Paid::class);

    expect($payment->fresh()->purpose)->toBe(PaymentPurpose::REGISTRATION_FEE);
});
