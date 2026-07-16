<?php

declare(strict_types=1);

use App\Exceptions\StalePaymentStateException;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\Support\Payments\LockPayment;
use Illuminate\Support\Facades\DB;

/**
 * Covers LockPayment's state verification and scope-bypass behaviour, which are engine
 * independent. The row-locking itself (two concurrent attempts producing exactly one paid
 * payment) needs real InnoDB `SELECT ... FOR UPDATE` semantics and cannot be proven on
 * SQLite, which has no such thing — see the real-engine concurrency test for that.
 */
it('returns the locked application and payment when the state matches', function () {
    $payment = Payment::factory()->create();

    DB::transaction(function () use ($payment) {
        $locked = LockPayment::inState($payment, Pending::class);

        expect($locked->payment->id)->toBe($payment->id)
            ->and($locked->application->id)->toBe($payment->application_id)
            ->and($locked->payment->status)->toBeInstanceOf(Pending::class);
    });
});

/**
 * The core protection: a caller whose in-memory state is stale — because another request
 * already resolved this attempt — must be rejected rather than allowed to apply a second
 * outcome on top of the first.
 */
it('rejects a caller whose in-memory state is stale', function () {
    $payment = Payment::factory()->create();

    // Another request settles the attempt behind this caller's back.
    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['status' => Paid::$name]);

    expect(fn () => DB::transaction(fn () => LockPayment::inState($payment, Pending::class)))
        ->toThrow(StalePaymentStateException::class);
});

it('rejects a state mismatch in either direction', function () {
    $payment = Payment::factory()->awaitingVerification()->create();

    expect(fn () => DB::transaction(fn () => LockPayment::inState($payment, Pending::class)))
        ->toThrow(StalePaymentStateException::class);

    DB::transaction(function () use ($payment) {
        expect(LockPayment::inState($payment, AwaitingVerification::class)->payment->id)->toBe($payment->id);
    });
});

it('rejects a payment that no longer exists', function () {
    $payment = Payment::factory()->create();
    Payment::withoutGlobalScopes()->whereKey($payment->id)->delete();

    expect(fn () => DB::transaction(fn () => LockPayment::inState($payment, Pending::class)))
        ->toThrow(StalePaymentStateException::class);
});

/**
 * BranchScope is a presentation filter. If it could hide a row here, a user in another
 * branch would enforce the one-active/one-paid invariants against a partial view of the
 * table — and a second paid attempt could be created simply because the first was invisible.
 */
it('finds the rows regardless of the acting user\'s branch scope', function () {
    $application = Application::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $payment = Payment::factory()->forApplication($application)->create();

    $this->actingAs(User::factory()->create(['branch_id' => Branch::factory()->create()->id]));

    expect(Payment::query()->count())->toBe(0);

    DB::transaction(function () use ($payment) {
        expect(LockPayment::inState($payment, Pending::class)->payment->id)->toBe($payment->id);
    });
});

it('locks an application for creation without needing an existing payment', function () {
    $application = Application::factory()->create();

    DB::transaction(function () use ($application) {
        expect(LockPayment::application($application)->id)->toBe($application->id);
    });
});

it('finds the application for locking regardless of branch scope', function () {
    $application = Application::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs(User::factory()->create(['branch_id' => Branch::factory()->create()->id]));

    expect(Application::query()->count())->toBe(0);

    DB::transaction(function () use ($application) {
        expect(LockPayment::application($application)->id)->toBe($application->id);
    });
});
