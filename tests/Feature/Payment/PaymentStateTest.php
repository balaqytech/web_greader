<?php

declare(strict_types=1);

use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\States\Payments\Rejected;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

const PAYMENT_STATES = [Pending::class, AwaitingVerification::class, Paid::class, Failed::class, Rejected::class, Expired::class];

it('defaults a new attempt to pending', function () {
    $payment = Payment::factory()->create(['status' => null]);

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

it('persists each state by its snake_case name', function (string $stateClass, string $name) {
    expect($stateClass::$name)->toBe($name);
})->with([
    [Pending::class, 'pending'],
    [AwaitingVerification::class, 'awaiting_verification'],
    [Paid::class, 'paid'],
    [Failed::class, 'failed'],
    [Rejected::class, 'rejected'],
    [Expired::class, 'expired'],
]);

it('stores the state name in the database, not the class', function () {
    $payment = Payment::factory()->paid()->create();

    expect(DB::table('payments')->where('id', $payment->id)->value('status'))->toBe('paid');
});

/**
 * The central safety property of the lifecycle, and a regression guard for the phases that
 * register the remaining edges: nothing may leave a terminal state. A retry is a new attempt,
 * never a revived dead one — and `Paid` in particular must never be transitioned out of,
 * since refunds are out of scope for this domain.
 */
it('never allows any transition out of a terminal state', function (string $terminalState) {
    $payment = Payment::factory()->create(['status' => $terminalState::$name]);

    foreach (PAYMENT_STATES as $target) {
        expect($payment->status->canTransitionTo($target))->toBeFalse();
    }
})->with([Paid::class, Failed::class, Rejected::class, Expired::class]);

it('refuses an unregistered transition at runtime rather than silently allowing it', function () {
    $payment = Payment::factory()->paid()->create();

    expect(fn () => $payment->status->transitionTo(Pending::class))
        ->toThrow(TransitionNotFound::class);
});

it('exposes a label and colour for every state', function (string $stateClass) {
    $payment = Payment::factory()->create();
    $state = new $stateClass($payment);

    expect($state->getLabel())->toBeString()->not->toBeEmpty()
        ->and($state->getColor())->toBeString()->not->toBeEmpty();
})->with(PAYMENT_STATES);

it('translates every state label, leaving no raw translation key', function (string $stateClass) {
    $state = new $stateClass(Payment::factory()->create());

    expect($state->getLabel())->not->toContain('admin.payment.states');
})->with(PAYMENT_STATES);
