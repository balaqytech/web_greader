<?php

use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;

it('translates every target state label without returning the raw key', function (string $stateClass) {
    $label = (new $stateClass(new Application))->getLabel();

    expect($label)->not->toBe('admin.application.states.'.$stateClass::$name)
        ->and($label)->not->toStartWith('admin.');
})->with([
    AwaitingRegistrationFee::class,
    AwaitingApplicationCompletion::class,
    AwaitingContractSignature::class,
    AwaitingBranchReview::class,
    CorrectionRequested::class,
    Accepted::class,
    Rejected::class,
    Cancelled::class,
]);

it('preserves legacy state labels for historical activity records', function (string $name) {
    expect(__("admin.application.states.{$name}"))->not->toBe("admin.application.states.{$name}");
})->with(['draft', 'submitted', 'waiting_contract_signature', 'under_review']);

it('translates every new alert key without returning the raw key', function (string $key) {
    expect(__("alerts.application.{$key}"))->not->toBe("alerts.application.{$key}");
})->with([
    'student_name_required',
    'student_civil_number_required',
    'guardian_required',
    'contract_not_signed',
    'contract_missing',
    'application_contract_uploaded_by_staff',
    'rejection_reason_required',
    'cancellation_note_required',
]);
