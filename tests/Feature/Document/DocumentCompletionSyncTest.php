<?php

declare(strict_types=1);

use App\Exceptions\UnpaidRegistrationFeeException;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Documents\Missing;
use App\States\Payments\Paid;
use App\Support\Payments\Evidence\ThawaniSettlementEvidence;

function documentCount(Application $application): int
{
    return ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->count();
}

function settlementEvidence(Payment $payment): ThawaniSettlementEvidence
{
    return new ThawaniSettlementEvidence(
        sessionId: $payment->provider_session_id ?? 'sess_test_'.$payment->reference,
        clientReference: $payment->reference,
        amount: $payment->money(),
        currency: $payment->currency,
        payload: [],
    );
}

it('synchronises document requirements when the fee gate advances an application', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create(['is_transfer_student' => false]);
    $payment = Payment::factory()->forApplication($application)->pending()->create();

    $payment->status->transitionTo(Paid::class, settlementEvidence($payment));

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and(documentCount($application))->toBe(8);
});

it('synchronises document requirements when a contract stage is reopened to completion', function () {
    $application = Application::factory()->awaitingContractSignature()->create(['is_transfer_student' => true]);

    expect(documentCount($application))->toBe(0);

    $application->status->transitionTo(AwaitingApplicationCompletion::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and(documentCount($application))->toBe(9);
});

it('rolls back document creation if the advancing transition fails', function () {
    // A non-pending payment cannot settle: the transition throws, and nothing — neither the
    // state change nor any document row — is left behind.
    $application = Application::factory()->awaitingRegistrationFee()->create();

    expect(fn () => $application->status->transitionTo(AwaitingApplicationCompletion::class))
        ->toThrow(UnpaidRegistrationFeeException::class);

    expect($application->fresh()->status)->not->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and(documentCount($application))->toBe(0);
});

it('leaves already-created requirements untouched when a reopened application re-enters completion', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->pending()->create();
    $payment->status->transitionTo(Paid::class, settlementEvidence($payment));

    // Upload one document, then push forward and reopen — the sync on reopening must not reset it.
    $document = ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)->first();
    $document->update(['is_required' => true]);

    expect($document->status)->toBeInstanceOf(Missing::class)
        ->and(documentCount($application))->toBe(8);
});
