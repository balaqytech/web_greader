<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Models\Application;
use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\PaymentState;
use App\States\Payments\Pending;
use App\States\Payments\Rejected;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

it('creates a pending thawani registration-fee attempt by default', function () {
    $payment = Payment::factory()->create();

    expect($payment->status)->toBeInstanceOf(Pending::class)
        ->and($payment->method)->toBe(PaymentMethod::THAWANI)
        ->and($payment->purpose)->toBe(PaymentPurpose::REGISTRATION_FEE)
        ->and($payment->currency)->toBe('OMR');
});

it('assigns a unique lowercase ulid reference on create', function () {
    $one = Payment::factory()->create();
    $two = Payment::factory()->create();

    expect($one->reference)->toHaveLength(26)
        ->and($one->reference)->toBe(strtolower($one->reference))
        ->and($one->reference)->not->toBe($two->reference);
});

/**
 * The public identifier must not be the auto-increment id: an enumerable reference lets a
 * caller count upwards to discover other applications' payments.
 */
it('uses the ulid reference as the route key, never the numeric id', function () {
    $payment = Payment::factory()->create();

    expect($payment->getRouteKeyName())->toBe('reference')
        ->and($payment->getRouteKey())->toBe($payment->reference)
        ->and($payment->getRouteKey())->not->toBe($payment->id);
});

it('does not overwrite an explicitly supplied reference', function () {
    $payment = Payment::factory()->create(['reference' => '01hxxxxxxxxxxxxxxxxxxxxxxx']);

    expect($payment->reference)->toBe('01hxxxxxxxxxxxxxxxxxxxxxxx');
});

it('keeps the denormalised branch in step with the application', function () {
    $application = Application::factory()->create();

    $payment = Payment::factory()->forApplication($application)->create();

    expect($payment->branch_id)->toBe($application->branch_id);
});

it('derives the branch from a generated application too', function () {
    $payment = Payment::factory()->create();

    expect($payment->branch_id)
        ->not->toBeNull()
        ->toBe(Application::withoutGlobalScopes()->find($payment->application_id)->branch_id);
});

/**
 * A float cast would reintroduce binary rounding into money that has to reconcile to the
 * baisa against the provider.
 */
it('keeps the amount an exact decimal string, never a float', function (string $amount, int $baisa) {
    $payment = Payment::factory()->amount($amount)->create();

    expect($payment->fresh()->amount)->toBeString()->toBe($amount)
        ->and($payment->fresh()->money()->toBaisa())->toBe($baisa);
})->with([
    ['25.000', 25000],
    ['0.070', 70],
    ['1.005', 1005],
    ['150.750', 150750],
]);

it('stores the amount at full precision in the database', function () {
    $payment = Payment::factory()->amount('1.005')->create();

    expect((string) DB::table('payments')->where('id', $payment->id)->value('amount'))
        ->toContain('1.005');
});

it('casts the state, method and purpose', function () {
    $payment = Payment::factory()->bankTransfer()->awaitingVerification()->create();

    expect($payment->fresh()->status)->toBeInstanceOf(AwaitingVerification::class)
        ->and($payment->fresh()->method)->toBe(PaymentMethod::BANK_TRANSFER)
        ->and($payment->fresh()->purpose)->toBe(PaymentPurpose::REGISTRATION_FEE);
});

it('exposes each factory state', function (string $factoryState, string $expectedState) {
    $payment = Payment::factory()->{$factoryState}()->create();

    expect($payment->fresh()->status)->toBeInstanceOf($expectedState);
})->with([
    ['pending', Pending::class],
    ['awaitingVerification', AwaitingVerification::class],
    ['paid', Paid::class],
    ['failed', Failed::class],
    ['rejected', Rejected::class],
    ['expired', Expired::class],
]);

it('never produces a reasonless rejection or failure', function () {
    expect(Payment::factory()->rejected()->create()->rejection_reason)->not->toBeEmpty()
        ->and(Payment::factory()->failed()->create()->failure_reason)->not->toBeEmpty();
});

it('models a rejected attempt as a reviewed bank transfer', function () {
    $payment = Payment::factory()->rejected()->create();

    expect($payment->method)->toBe(PaymentMethod::BANK_TRANSFER)
        ->and($payment->receipt_path)->not->toBeNull()
        ->and($payment->verified_at)->not->toBeNull();
});

it('reports paid only when paid', function () {
    expect(Payment::factory()->paid()->create()->isPaid())->toBeTrue()
        ->and(Payment::factory()->pending()->create()->isPaid())->toBeFalse()
        ->and(Payment::factory()->failed()->create()->isPaid())->toBeFalse();
});

it('classifies active and terminal states', function () {
    $payment = Payment::factory()->create();

    expect((new Pending($payment))->isActive())->toBeTrue()
        ->and((new AwaitingVerification($payment))->isActive())->toBeTrue()
        ->and((new Pending($payment))->isTerminal())->toBeFalse()
        ->and((new Paid($payment))->isTerminal())->toBeTrue()
        ->and((new Failed($payment))->isTerminal())->toBeTrue()
        ->and((new Rejected($payment))->isTerminal())->toBeTrue()
        ->and((new Expired($payment))->isTerminal())->toBeTrue();
});

/**
 * Guards the reason activeStates()/terminalStates() are declared once: every state must be
 * exactly one of the two, or the in-memory rule and the SQL scopes would disagree about an
 * unclassified state.
 */
it('classifies every state as exactly one of active or terminal', function () {
    $all = [Pending::class, AwaitingVerification::class, Paid::class, Failed::class, Rejected::class, Expired::class];

    expect(array_merge(PaymentState::activeStates(), PaymentState::terminalStates()))
        ->toEqualCanonicalizing($all)
        ->and(array_intersect(PaymentState::activeStates(), PaymentState::terminalStates()))
        ->toBeEmpty();
});

it('scopes to active attempts', function () {
    $application = Application::factory()->create();

    Payment::factory()->forApplication($application)->pending()->create();
    Payment::factory()->forApplication($application)->awaitingVerification()->create();
    Payment::factory()->forApplication($application)->paid()->create();
    Payment::factory()->forApplication($application)->failed()->create();
    Payment::factory()->forApplication($application)->expired()->create();

    expect(Payment::query()->active()->count())->toBe(2)
        ->and(Payment::query()->terminal()->count())->toBe(3)
        ->and(Payment::query()->paid()->count())->toBe(1);
});

it('scopes to registration-fee attempts', function () {
    Payment::factory()->count(2)->create();

    expect(Payment::query()->forRegistrationFee()->count())->toBe(2);
});

/**
 * Sequential retries: a terminal attempt is kept as history and a retry is a new row, so an
 * application accumulates attempts rather than overwriting them.
 */
it('permits sequential retries after a terminal attempt, retaining history', function () {
    $application = Application::factory()->create();

    Payment::factory()->forApplication($application)->failed()->create();
    Payment::factory()->forApplication($application)->expired()->create();
    Payment::factory()->forApplication($application)->rejected()->create();
    $current = Payment::factory()->forApplication($application)->pending()->create();

    expect(Payment::query()->where('application_id', $application->id)->count())->toBe(4)
        ->and(Payment::query()->where('application_id', $application->id)->active()->count())->toBe(1)
        ->and(Payment::query()->where('application_id', $application->id)->active()->first()->is($current))->toBeTrue();
});

/**
 * `audit.console` is false, and tests run in console — so auditing has to be switched on
 * explicitly here or this would assert nothing. Worth knowing beyond the test: writes made
 * from Artisan commands and queued workers are NOT audited under the current config, so any
 * future background payment mutation would leave no trail.
 */
it('audits every payment write, because a payment is financial evidence', function () {
    config(['audit.console' => true]);

    $payment = Payment::factory()->create();

    $auditCount = fn (): int => Audit::query()
        ->where('auditable_type', $payment->getMorphClass())
        ->where('auditable_id', $payment->id)
        ->count();

    expect($auditCount())->toBe(1);

    $payment->update(['cash_reference' => 'RCPT-1']);

    expect($auditCount())->toBe(2);

    $latest = Audit::query()
        ->where('auditable_type', $payment->getMorphClass())
        ->where('auditable_id', $payment->id)
        ->latest('id')
        ->first();

    expect($latest->event)->toBe('updated')
        ->and($latest->new_values)->toHaveKey('cash_reference')
        ->and($latest->new_values['cash_reference'])->toBe('RCPT-1');
});

it('relates to its application, branch and actors', function () {
    $payment = Payment::factory()->create();

    expect($payment->application)->toBeInstanceOf(Application::class)
        ->and($payment->branch->id)->toBe($payment->branch_id)
        ->and($payment->verifiedBy)->toBeNull()
        ->and($payment->createdBy)->toBeNull();
});

it('enforces a unique idempotency key', function () {
    Payment::factory()->create(['idempotency_key' => 'user:1:abc']);

    expect(fn () => Payment::factory()->create(['idempotency_key' => 'user:1:abc']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows many attempts without an idempotency key', function () {
    Payment::factory()->count(3)->create(['idempotency_key' => null]);

    expect(Payment::query()->whereNull('idempotency_key')->count())->toBe(3);
});

it('exposes only valid HTTPS checkout URLs to staff surfaces', function () {
    $payment = Payment::factory()->make();

    $payment->provider_checkout_url = 'https://checkout.thawani.om/pay/session';
    expect($payment->safeCheckoutUrl())->toBe('https://checkout.thawani.om/pay/session');

    $payment->provider_checkout_url = 'http://checkout.thawani.om/pay/session';
    expect($payment->safeCheckoutUrl())->toBeNull();

    $payment->provider_checkout_url = 'javascript:alert(1)';
    expect($payment->safeCheckoutUrl())->toBeNull();
});

/**
 * A payment is evidence that money changed hands; deleting the application it belongs to
 * must fail loudly rather than silently cascade the record away.
 */
it('refuses to cascade-delete a payment when its application is deleted', function () {
    $application = Application::factory()->create();
    Payment::factory()->forApplication($application)->create();

    expect(fn () => $application->delete())->toThrow(QueryException::class);
});
