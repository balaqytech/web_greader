<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\States\Payments\AwaitingVerification;
use App\Support\Money\OmrAmount;
use App\Support\Settings\PaymentSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    app(PaymentSettings::class)->setRegistrationFee(OmrAmount::fromString('25.000'));
    app(PaymentSettings::class)->setBankTransferInstructions('IBAN OM00 TEST');

    app()->instance(PaymentGateway::class, new FakePaymentGateway);

    $this->tokenUser = User::factory()->create();
    $created = $this->tokenUser->createToken('test', ['payments:initiate', 'payments:upload-receipt']);
    $this->token = $created->plainTextToken;

    RateLimiter::clear('payments:'.$created->accessToken->id);
});

function paymentApiApplication(array $overrides = []): Application
{
    return Application::factory()->awaitingRegistrationFee()->create(array_merge([
        'father_is_guardian' => true,
        'father_phone' => '99123456',
    ], $overrides));
}

function callInitiate(string $uri, ?string $token, array $payload, ?string $idempotencyKey = 'key-1'): TestResponse
{
    $headers = [];

    if ($idempotencyKey !== null) {
        $headers['Idempotency-Key'] = $idempotencyKey;
    }

    $request = test();

    if ($token !== null) {
        $request = $request->withHeader('Authorization', 'Bearer '.$token);
    }

    return $request->withHeaders($headers)->postJson($uri, $payload);
}

it('requires authentication', function () {
    $application = paymentApiApplication();

    callInitiate('/api/v1/payments/thawani', null, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ])->assertUnauthorized();
});

it('requires the payments:initiate ability', function () {
    $application = paymentApiApplication();
    $token = $this->tokenUser->createToken('no-abilities', ['some:other'])->plainTextToken;

    callInitiate('/api/v1/payments/thawani', $token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ])->assertForbidden();
});

it('requires an idempotency key', function () {
    $application = paymentApiApplication();

    callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ], idempotencyKey: null)->assertStatus(409);
});

it('404s on a reference and phone that do not both match', function () {
    $application = paymentApiApplication();

    callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '90000000',
    ])->assertNotFound();

    callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => 'APP-does-not-exist',
        'guardian_phone' => '99123456',
    ])->assertNotFound();
});

it('initiates a thawani checkout and exposes only the public fields', function () {
    $application = paymentApiApplication();

    $response = callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['reference', 'method', 'amount', 'currency', 'status', 'status_label', 'checkout_url', 'bank_instructions']]);

    expect($response->json('data.method'))->toBe('thawani')
        ->and($response->json('data.checkout_url'))->not->toBeNull()
        ->and($response->json('data.status'))->toBe('pending');

    $body = $response->getContent();
    expect($body)->not->toContain('provider_payload')
        ->and($body)->not->toContain($application->student_name)
        ->and(array_keys($response->json('data')))->toEqualCanonicalizing([
            'reference', 'method', 'amount', 'currency', 'status', 'status_label', 'checkout_url', 'bank_instructions',
        ]);
});

it('initiates a bank transfer with instructions and no checkout url', function () {
    $application = paymentApiApplication();

    $response = callInitiate('/api/v1/payments/bank-transfer', $this->token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ]);

    $response->assertCreated();

    expect($response->json('data.method'))->toBe('bank_transfer')
        ->and($response->json('data.checkout_url'))->toBeNull()
        ->and($response->json('data.bank_instructions'))->toBe('IBAN OM00 TEST');
});

it('returns the same payment for an exact replay and a conflict for a mismatched one', function () {
    $application = paymentApiApplication();
    $other = paymentApiApplication();

    $first = callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ], idempotencyKey: 'shared-key');

    $replay = callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ], idempotencyKey: 'shared-key');

    expect($replay->json('data.reference'))->toBe($first->json('data.reference'))
        ->and(Payment::withoutGlobalScopes()->count())->toBe(1);

    callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => $other->ref_no,
        'guardian_phone' => '99123456',
    ], idempotencyKey: 'shared-key')->assertStatus(409);
});

it('enforces a 5-per-minute rate limit per token', function () {
    for ($i = 0; $i < 5; $i++) {
        $application = paymentApiApplication();

        callInitiate('/api/v1/payments/thawani', $this->token, [
            'application_reference' => $application->ref_no,
            'guardian_phone' => '99123456',
        ], idempotencyKey: "key-{$i}")->assertCreated();
    }

    $application = paymentApiApplication();

    callInitiate('/api/v1/payments/thawani', $this->token, [
        'application_reference' => $application->ref_no,
        'guardian_phone' => '99123456',
    ], idempotencyKey: 'key-6')->assertStatus(429);
});

it('uploads a bank receipt and moves the attempt to awaiting verification', function () {
    $application = paymentApiApplication();
    $payment = Payment::factory()->forApplication($application)->bankTransfer()->pending()->create();

    $response = test()->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('Accept', 'application/json')
        ->post("/api/v1/payments/{$payment->reference}/receipt", [
            'application_reference' => $application->ref_no,
            'guardian_phone' => '99123456',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ]);

    $response->assertOk();
    expect($payment->fresh()->status)->toBeInstanceOf(AwaitingVerification::class)
        ->and($payment->fresh()->receipt_path)->not->toBeNull();
});

it('refuses a receipt upload for a payment that is not an eligible pending bank transfer', function () {
    $application = paymentApiApplication();
    $payment = Payment::factory()->forApplication($application)->thawani()->pending()->create();

    test()->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('Accept', 'application/json')
        ->post("/api/v1/payments/{$payment->reference}/receipt", [
            'application_reference' => $application->ref_no,
            'guardian_phone' => '99123456',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])->assertStatus(409);
});

it('rejects a receipt over the size limit or of the wrong type', function () {
    $application = paymentApiApplication();
    $payment = Payment::factory()->forApplication($application)->bankTransfer()->pending()->create();

    test()->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('Accept', 'application/json')
        ->post("/api/v1/payments/{$payment->reference}/receipt", [
            'application_reference' => $application->ref_no,
            'guardian_phone' => '99123456',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 6000, 'application/pdf'),
        ])->assertStatus(422);

    test()->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('Accept', 'application/json')
        ->post("/api/v1/payments/{$payment->reference}/receipt", [
            'application_reference' => $application->ref_no,
            'guardian_phone' => '99123456',
            'receipt' => UploadedFile::fake()->create('receipt.exe', 100, 'application/octet-stream'),
        ])->assertStatus(422);
});

it('has no cash route, since cash is staff-only', function () {
    callInitiate('/api/v1/payments/cash', $this->token, [])->assertStatus(404);
});
