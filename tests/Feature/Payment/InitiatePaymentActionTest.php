<?php

declare(strict_types=1);

use App\Actions\Payments\InitiatePaymentAction;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentInitiationException;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Payments\Failed;
use App\States\Payments\Pending;
use App\Support\Money\OmrAmount;
use App\Support\Settings\PaymentSettings;
use App\Support\Settings\SettingsRepository;

beforeEach(function () {
    app(PaymentSettings::class)->setRegistrationFee(OmrAmount::fromString('25.000'));
    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
});

function initiate(Application $application, PaymentMethod $method = PaymentMethod::THAWANI, ?User $actor = null, ?string $key = null, ?string $hash = null): Payment
{
    return app(InitiatePaymentAction::class)->execute(
        new InitiatePaymentDTO($application, $method, $actor, $key, $hash)
    );
}

it('creates a pending attempt with a checkout url for thawani', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $payment = initiate($application);

    expect($payment->status)->toBeInstanceOf(Pending::class)
        ->and($payment->method)->toBe(PaymentMethod::THAWANI)
        ->and($payment->purpose)->toBe(PaymentPurpose::REGISTRATION_FEE)
        ->and($payment->provider_checkout_url)->not->toBeNull()
        ->and($payment->provider_session_id)->not->toBeNull();
});

/**
 * The fee is snapshotted, so a later change cannot alter what an existing attempt was for.
 */
it('snapshots the configured fee onto the attempt', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $payment = initiate($application);
    expect($payment->amount)->toBe('25.000');

    app(PaymentSettings::class)->setRegistrationFee(OmrAmount::fromString('99.000'));

    expect($payment->fresh()->amount)->toBe('25.000');
});

it('sends the snapshotted amount to the provider, exactly', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    app(PaymentSettings::class)->setRegistrationFee(OmrAmount::fromString('1.005'));

    $payment = initiate($application);

    expect($this->gateway->retrieveSession($payment->provider_session_id)->amount?->value)->toBe('1.005');
});

/**
 * No default fee: a guessed or zero one would let applications through the gate for free.
 */
it('refuses to create an attempt while the fee is unconfigured', function () {
    app(PaymentSettings::class)->setRegistrationFee(OmrAmount::fromString('25.000'));
    Setting::query()->where('key', PaymentSettings::REGISTRATION_FEE_AMOUNT)->update(['value' => null]);
    app(SettingsRepository::class)->flush();

    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect(fn () => initiate($application))->toThrow(PaymentInitiationException::class);

    expect(Payment::query()->count())->toBe(0);
});

it('refuses bank transfer until its instructions are configured', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect(fn () => initiate($application, PaymentMethod::BANK_TRANSFER))
        ->toThrow(PaymentInitiationException::class);

    app(PaymentSettings::class)->setBankTransferInstructions('IBAN OM00');

    expect(initiate($application, PaymentMethod::BANK_TRANSFER)->method)->toBe(PaymentMethod::BANK_TRANSFER);
});

it('never calls the provider for a bank transfer or cash attempt', function (PaymentMethod $method) {
    app(PaymentSettings::class)->setBankTransferInstructions('IBAN OM00');
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $payment = initiate($application, $method);

    expect($payment->provider_session_id)->toBeNull()
        ->and($payment->provider_checkout_url)->toBeNull();
})->with([PaymentMethod::BANK_TRANSFER, PaymentMethod::CASH]);

it('refuses an application that is not awaiting the fee', function (string $factoryState) {
    $application = Application::factory()->{$factoryState}()->create();

    expect(fn () => initiate($application))->toThrow(PaymentInitiationException::class);

    expect(Payment::query()->count())->toBe(0);
})->with(['awaitingApplicationCompletion', 'awaitingContractSignature', 'accepted', 'cancelled']);

/**
 * One active attempt per application: a second checkout for the same fee is how a guardian
 * ends up paying twice.
 */
it('refuses a second active attempt on the same application', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    initiate($application);

    expect(fn () => initiate($application))->toThrow(PaymentInitiationException::class);

    expect(Payment::query()->where('application_id', $application->id)->count())->toBe(1);
});

it('permits a fresh attempt once the previous one is terminal', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $first = initiate($application);
    $first->status->transitionTo(Failed::class, 'declined');

    $second = initiate($application);

    expect($second->id)->not->toBe($first->id)
        ->and(Payment::query()->where('application_id', $application->id)->count())->toBe(2);
});

it('denormalises the branch from the locked application, not the request', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect(initiate($application)->branch_id)->toBe($application->branch_id);
});

it('records the acting user, and leaves it null for a service caller', function () {
    $user = User::factory()->create();

    expect(initiate(Application::factory()->awaitingRegistrationFee()->create(), actor: $user)->created_by)->toBe($user->id)
        ->and(initiate(Application::factory()->awaitingRegistrationFee()->create())->created_by)->toBeNull();
});

it('returns the original payment for an exact replay', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $first = initiate($application, key: 'user:1:abc', hash: 'hash-1');
    $replay = initiate($application, key: 'user:1:abc', hash: 'hash-1');

    expect($replay->id)->toBe($first->id)
        ->and(Payment::query()->count())->toBe(1);
});

/**
 * Returning the original here would hand the caller someone else's payment; creating a new
 * one would defeat the key. Refusing is the only honest answer.
 */
it('refuses a key reused for a different request', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    initiate($application, key: 'user:1:abc', hash: 'hash-1');

    expect(fn () => initiate($application, key: 'user:1:abc', hash: 'hash-2'))
        ->toThrow(PaymentInitiationException::class);

    expect(Payment::query()->count())->toBe(1);
});

/**
 * An un-namespaced key would be an enumeration hole straight into other callers' payments.
 */
it('namespaces an idempotency key by the acting principal', function () {
    expect(InitiatePaymentAction::namespacedKey('1', 'user:7'))->toBe('user:7:1')
        ->and(InitiatePaymentAction::namespacedKey('1', 'token:9'))->not->toBe(InitiatePaymentAction::namespacedKey('1', 'user:7'))
        ->and(InitiatePaymentAction::namespacedKey('1', null))->toBe('anonymous:1');
});

it('does not let two principals collide on the same raw key', function () {
    $one = Application::factory()->awaitingRegistrationFee()->create();
    $two = Application::factory()->awaitingRegistrationFee()->create();

    $a = initiate($one, key: InitiatePaymentAction::namespacedKey('1', 'user:1'), hash: 'h');
    $b = initiate($two, key: InitiatePaymentAction::namespacedKey('1', 'user:2'), hash: 'h');

    expect($a->id)->not->toBe($b->id);
});

/**
 * A final rejection frees the application for a fresh attempt immediately.
 */
it('fails the attempt when the provider rejects the checkout outright', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $this->gateway->willFailWith(PaymentGatewayException::rejected('invalid amount'));

    expect(fn () => initiate($application))->toThrow(PaymentGatewayException::class);

    $payment = Payment::query()->where('application_id', $application->id)->firstOrFail();

    expect($payment->status)->toBeInstanceOf(Failed::class)
        ->and($payment->failure_reason)->toContain('invalid amount');
});

/**
 * The provider may have created the session regardless of our never hearing back, so the
 * attempt must stay pending for reconciliation to resolve by client reference. Failing it
 * here could abandon a session the guardian is about to pay.
 */
it('leaves the attempt pending when the provider is unreachable', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $this->gateway->willFailWith(PaymentGatewayException::unreachable(new RuntimeException('timeout')));

    expect(fn () => initiate($application))->toThrow(PaymentGatewayException::class);

    $payment = Payment::query()->where('application_id', $application->id)->firstOrFail();

    expect($payment->status)->toBeInstanceOf(Pending::class)
        ->and($payment->provider_session_id)->toBeNull();
});

/**
 * The attempt's own public reference is the client reference, which is what makes an
 * unresolved attempt recoverable at all.
 */
it('gives the provider the attempt\'s public reference as the client reference', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $payment = initiate($application);

    expect($this->gateway->retrieveByClientReference($payment->reference)?->sessionId)
        ->toBe($payment->provider_session_id);
});

it('does not advance the application merely by creating an attempt', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    initiate($application);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});
