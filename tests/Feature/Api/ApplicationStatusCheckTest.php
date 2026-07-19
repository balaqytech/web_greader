<?php

use App\Enums\PaymentMethod;
use App\Models\Application;
use App\Models\Payment;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;
use App\States\Payments\Paid;
use App\States\Payments\Pending;

function statusApplication(string $statusName): Application
{
    return Application::factory()->create([
        'status' => $statusName,
        'father_is_guardian' => true,
        'father_phone' => '99123456',
        'student_name' => 'Secret Student',
    ]);
}

function statusCheck(string $reference, string $phone)
{
    [, $token] = fasihServiceToken();

    return test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/applications/status-check', [
            'application_reference' => $reference,
            'guardian_phone' => $phone,
        ]);
}

it('maps every application state to the correct next step', function (string $stateName, string $expected) {
    $application = statusApplication($stateName);

    statusCheck($application->ref_no, '99123456')
        ->assertOk()
        ->assertJsonPath('data.status', $stateName)
        ->assertJsonPath('data.next_step', $expected);
})->with([
    'awaiting fee' => [AwaitingRegistrationFee::$name, 'pay_registration_fee'],
    'awaiting completion' => [AwaitingApplicationCompletion::$name, 'complete_application_data'],
    'awaiting contract' => [AwaitingContractSignature::$name, 'sign_contract'],
    'awaiting review' => [AwaitingBranchReview::$name, 'await_branch_review'],
    'correction requested' => [CorrectionRequested::$name, 'complete_corrections'],
    'accepted' => [Accepted::$name, 'completed'],
    'rejected' => [Rejected::$name, 'none'],
    'cancelled' => [Cancelled::$name, 'none'],
]);

it('answers a generic 404 for a wrong phone, wrong reference, or unknown application', function () {
    $application = statusApplication(AwaitingRegistrationFee::$name);

    statusCheck($application->ref_no, '90000000')->assertNotFound();
    statusCheck('APP-does-not-exist', '99123456')->assertNotFound();
});

it('normalizes the guardian phone before matching', function () {
    $application = statusApplication(AwaitingRegistrationFee::$name);

    // Stored as 99123456; a local 0-prefixed form normalizes to the same number.
    statusCheck($application->ref_no, '099123456')->assertOk()
        ->assertJsonPath('data.application_reference', $application->ref_no);
});

it('projects the latest registration payment and ignores older ones', function () {
    $application = statusApplication(AwaitingRegistrationFee::$name);

    Payment::factory()->forApplication($application)->create([
        'method' => PaymentMethod::BANK_TRANSFER,
        'status' => Pending::$name,
    ]);
    $latest = Payment::factory()->forApplication($application)->create([
        'method' => PaymentMethod::THAWANI,
        'status' => Paid::$name,
    ]);

    statusCheck($application->ref_no, '99123456')->assertOk()
        ->assertJsonPath('data.registration_payment.reference', $latest->reference)
        ->assertJsonPath('data.registration_payment.method', 'thawani')
        ->assertJsonPath('data.registration_payment.status', 'paid');
});

it('returns a null registration payment when there is none', function () {
    $application = statusApplication(AwaitingRegistrationFee::$name);

    statusCheck($application->ref_no, '99123456')->assertOk()
        ->assertJsonPath('data.registration_payment', null);
});

it('exposes only the allowlisted fields and never PII or internals', function () {
    $application = statusApplication(AwaitingContractSignature::$name);

    $response = statusCheck($application->ref_no, '99123456');

    $response->assertOk();

    expect(array_keys($response->json('data')))->toEqualCanonicalizing([
        'application_reference', 'status', 'status_label', 'next_step', 'next_step_label', 'registration_payment',
    ]);

    $response->assertDontSee('Secret Student', escape: false)
        ->assertDontSee('99123456', escape: false)
        ->assertDontSee('provider_payload', escape: false);
});
