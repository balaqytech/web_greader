<?php

declare(strict_types=1);

use App\Actions\Payments\ResolvePaymentFromProviderAction;
use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Enums\PaymentMethod;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\InvalidSettlementEvidenceException;
use App\Exceptions\PaymentInitiationException;
use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\States\Payments\Rejected;
use App\Support\Money\OmrAmount;
use App\Support\Payments\Evidence\BankTransferVerificationEvidence;
use App\Support\Payments\Evidence\CashSettlementEvidence;
use App\Support\Payments\Evidence\ThawaniSettlementEvidence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $this->gateway);
});

/**
 * Blocking defect: a "paid" provider response must be bound to *this* attempt — session id,
 * client reference, exact amount, and currency — before it is ever trusted.
 */
it('refuses to settle when the provider session id does not match the stored session', function () {
    Log::shouldReceive('error')->once();

    $payment = Payment::factory()->thawani()->create();

    // The provider answers with a "paid" session that does not match this attempt's own
    // stored session id — the exact shape of a mix-up, not a forgery.
    $mismatched = new ProviderSessionStatusDTO(
        sessionId: 'sess_'.Str::random(20),
        outcome: ProviderPaymentOutcome::PAID,
        amount: $payment->money(),
        clientReference: $payment->reference,
        currency: 'OMR',
    );

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('retrieveSession')->with($payment->provider_session_id)->andReturn($mismatched);
    app()->instance(PaymentGateway::class, $gateway);

    $result = app(ResolvePaymentFromProviderAction::class)->execute($payment);

    expect($result->status)->toBeInstanceOf(Pending::class);
});

it('refuses to settle when the client reference does not belong to this attempt', function () {
    Log::shouldReceive('error')->once();

    $payment = Payment::factory()->thawani()->create();

    $status = new ProviderSessionStatusDTO(
        sessionId: $payment->provider_session_id,
        outcome: ProviderPaymentOutcome::PAID,
        amount: $payment->money(),
        clientReference: 'not-this-attempt',
        currency: 'OMR',
    );

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('retrieveSession')->andReturn($status);
    app()->instance(PaymentGateway::class, $gateway);

    $result = app(ResolvePaymentFromProviderAction::class)->execute($payment);

    expect($result->status)->toBeInstanceOf(Pending::class);
});

it('refuses to settle when the charged amount does not match the snapshotted amount', function () {
    Log::shouldReceive('error')->once();

    $payment = Payment::factory()->thawani()->amount('25.000')->create();

    $status = new ProviderSessionStatusDTO(
        sessionId: $payment->provider_session_id,
        outcome: ProviderPaymentOutcome::PAID,
        amount: OmrAmount::fromString('10.000'),
        clientReference: $payment->reference,
        currency: 'OMR',
    );

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('retrieveSession')->andReturn($status);
    app()->instance(PaymentGateway::class, $gateway);

    $result = app(ResolvePaymentFromProviderAction::class)->execute($payment);

    expect($result->status)->toBeInstanceOf(Pending::class);
});

it('refuses to settle when the provider currency is not OMR', function () {
    Log::shouldReceive('error')->once();

    $payment = Payment::factory()->thawani()->create();

    $status = new ProviderSessionStatusDTO(
        sessionId: $payment->provider_session_id,
        outcome: ProviderPaymentOutcome::PAID,
        amount: $payment->money(),
        clientReference: $payment->reference,
        currency: 'USD',
    );

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('retrieveSession')->andReturn($status);
    app()->instance(PaymentGateway::class, $gateway);

    $result = app(ResolvePaymentFromProviderAction::class)->execute($payment);

    expect($result->status)->toBeInstanceOf(Pending::class);
});

it('preserves sanitised evidence on a discrepancy without settling', function () {
    Log::shouldReceive('error')->once();

    $payment = Payment::factory()->thawani()->create();

    $status = new ProviderSessionStatusDTO(
        sessionId: $payment->provider_session_id,
        outcome: ProviderPaymentOutcome::PAID,
        amount: OmrAmount::fromString('999.000'),
        clientReference: $payment->reference,
        currency: 'OMR',
        payload: ['session_id' => $payment->provider_session_id, 'payment_status' => 'paid'],
    );

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('retrieveSession')->andReturn($status);
    app()->instance(PaymentGateway::class, $gateway);

    app(ResolvePaymentFromProviderAction::class)->execute($payment);

    expect($payment->fresh()->provider_payload)->not->toBeNull()
        ->and($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

it('persists a recovered session id and sets verified_at on a genuine settlement', function () {
    $payment = Payment::factory()->pending()->create([
        'method' => PaymentMethod::THAWANI,
        'provider_session_id' => null,
    ]);

    $session = $this->gateway->createCheckout(new CheckoutRequestDTO(
        clientReference: $payment->reference,
        amount: $payment->money(),
        productName: 'x',
        successUrl: 'https://app.test/s',
        cancelUrl: 'https://app.test/c',
    ));
    $this->gateway->settle($session->sessionId, ProviderPaymentOutcome::PAID);

    $result = app(ResolvePaymentFromProviderAction::class)->execute($payment);

    expect($result->status)->toBeInstanceOf(Paid::class)
        ->and($result->provider_session_id)->toBe($session->sessionId)
        ->and($result->verified_at)->not->toBeNull();
});

/**
 * Blocking defect: a stale-state loser (the browser return and reconciliation racing on the
 * same attempt) must refresh and return the persisted result rather than error.
 */
it('does not raise when it loses a settlement race, and returns the persisted result', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->thawani()->pending()->create();

    $session = $this->gateway->createCheckout(new CheckoutRequestDTO(
        clientReference: $payment->reference,
        amount: $payment->money(),
        productName: 'x',
        successUrl: 'https://app.test/s',
        cancelUrl: 'https://app.test/c',
    ));
    Payment::withoutGlobalScopes()->whereKey($payment->id)->update(['provider_session_id' => $session->sessionId]);
    $payment = $payment->fresh();
    $this->gateway->settle($session->sessionId, ProviderPaymentOutcome::PAID);

    // The row is already settled underneath, but this action's in-memory copy still says
    // Pending — exactly what an overlapping request sees.
    $winner = $payment->fresh()->status->transitionTo(Paid::class, new ThawaniSettlementEvidence(
        sessionId: $session->sessionId,
        clientReference: $payment->reference,
        amount: $payment->money(),
        currency: 'OMR',
    ));
    expect($winner->status)->toBeInstanceOf(Paid::class);

    $result = app(ResolvePaymentFromProviderAction::class)->execute($payment);

    expect($result->status)->toBeInstanceOf(Paid::class);
});

/**
 * Blocking defect: a transition to Paid must not be reachable without evidence matching the
 * payment's own method.
 */
it('refuses to settle a thawani payment with cash evidence', function () {
    $payment = Payment::factory()->thawani()->pending()->create();

    expect(fn () => $payment->status->transitionTo(Paid::class, new CashSettlementEvidence(
        confirmedBy: User::factory()->create(),
        reference: 'CASH-1',
        notes: 'received',
    )))->toThrow(InvalidSettlementEvidenceException::class);

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

it('settles a cash payment only through cash evidence and stamps the confirming user', function () {
    $payment = Payment::factory()->cash()->pending()->create();
    $staff = User::factory()->create();

    $result = $payment->status->transitionTo(Paid::class, new CashSettlementEvidence(
        confirmedBy: $staff,
        reference: 'CASH-42',
        notes: 'Collected at front desk',
    ));

    expect($result->status)->toBeInstanceOf(Paid::class)
        ->and($result->cash_reference)->toBe('CASH-42')
        ->and($result->cash_notes)->toBe('Collected at front desk')
        ->and($result->verified_by)->toBe($staff->id)
        ->and($result->verified_at)->not->toBeNull();
});

/**
 * Blocking defect: bank transfer must never reach Paid directly from Pending, even with
 * evidence whose method matches — it must always pass through AwaitingVerification first.
 */
it('refuses to settle a pending bank-transfer payment directly, even with matching evidence', function () {
    $payment = Payment::factory()->bankTransfer()->pending()->create();
    $finance = User::factory()->create();

    expect(fn () => $payment->status->transitionTo(Paid::class, new BankTransferVerificationEvidence($finance)))
        ->toThrow(InvalidSettlementEvidenceException::class);

    expect($payment->fresh()->status)->toBeInstanceOf(Pending::class);
});

it('moves a bank transfer through pending, awaiting verification, and paid', function () {
    $payment = Payment::factory()->bankTransfer()->pending()->create();
    $finance = User::factory()->create();

    $uploaded = $payment->status->transitionTo(AwaitingVerification::class, 'receipts/abc.pdf');
    expect($uploaded->status)->toBeInstanceOf(AwaitingVerification::class)
        ->and($uploaded->receipt_path)->toBe('receipts/abc.pdf');

    $verified = $uploaded->status->transitionTo(Paid::class, new BankTransferVerificationEvidence($finance));

    expect($verified->status)->toBeInstanceOf(Paid::class)
        ->and($verified->verified_by)->toBe($finance->id);
});

it('rejects a bank transfer receipt with a reason, and refuses a blank one', function () {
    $payment = Payment::factory()->awaitingVerification()->create();
    $finance = User::factory()->create();

    expect(fn () => $payment->status->transitionTo(Rejected::class, '', $finance))
        ->toThrow(InvalidArgumentException::class);
    expect($payment->fresh()->status)->toBeInstanceOf(AwaitingVerification::class);

    $rejected = $payment->status->transitionTo(Rejected::class, 'Receipt amount does not match', $finance);

    expect($rejected->status)->toBeInstanceOf(Rejected::class)
        ->and($rejected->rejection_reason)->toBe('Receipt amount does not match');
});

it('refuses to move a non-bank-transfer payment into awaiting verification', function () {
    $payment = Payment::factory()->thawani()->pending()->create();

    expect(fn () => $payment->status->transitionTo(AwaitingVerification::class, 'receipts/abc.pdf'))
        ->toThrow(InvalidSettlementEvidenceException::class);
});

/**
 * Blocking defect: idempotencyKey and requestHash must both be present or both be absent.
 */
it('refuses an idempotency key without a request hash, and vice versa', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect(fn () => new InitiatePaymentDTO($application, PaymentMethod::THAWANI, idempotencyKey: 'k'))
        ->toThrow(PaymentInitiationException::class);

    expect(fn () => new InitiatePaymentDTO($application, PaymentMethod::THAWANI, requestHash: 'h'))
        ->toThrow(PaymentInitiationException::class);
});

/**
 * Blocking defect: a Thawani session must belong to exactly one payment attempt.
 */
it('enforces a unique provider session id at the database level', function () {
    $sessionId = 'sess_'.Str::random(20);
    Payment::factory()->thawani()->create(['provider_session_id' => $sessionId]);

    expect(fn () => Payment::factory()->thawani()->create(['provider_session_id' => $sessionId]))
        ->toThrow(UniqueConstraintViolationException::class);
});
