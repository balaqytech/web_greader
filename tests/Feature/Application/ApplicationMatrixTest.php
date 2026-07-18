<?php

declare(strict_types=1);

use App\Actions\Corrections\CompleteCorrectionAction;
use App\Actions\Corrections\RequestCorrectionAction;
use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;
use App\States\Contracts\Signed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

uses(RefreshDatabase::class);

/**
 * The full §3.2 application-state matrix. Every ✔ cell is exercised end-to-end with real
 * prerequisites; every blank cell is asserted unreachable (throws, no side effect). This is the
 * consolidated assertion Phase 0 deliberately deferred until payment-gated, correction, and
 * version-aware transitions all existed.
 */

/**
 * @return array<string, class-string>
 */
function stateClasses(): array
{
    return [
        'awaiting_registration_fee' => AwaitingRegistrationFee::class,
        'awaiting_application_completion' => AwaitingApplicationCompletion::class,
        'awaiting_contract_signature' => AwaitingContractSignature::class,
        'awaiting_branch_review' => AwaitingBranchReview::class,
        'correction_requested' => CorrectionRequested::class,
        'accepted' => Accepted::class,
        'rejected' => Rejected::class,
        'cancelled' => Cancelled::class,
    ];
}

/**
 * @return array<string, list<string>>
 */
function allowedEdges(): array
{
    return [
        'awaiting_registration_fee' => ['awaiting_application_completion', 'cancelled'],
        'awaiting_application_completion' => ['awaiting_contract_signature', 'cancelled'],
        'awaiting_contract_signature' => ['awaiting_application_completion', 'awaiting_branch_review', 'cancelled'],
        'awaiting_branch_review' => ['accepted', 'rejected', 'correction_requested', 'cancelled'],
        'correction_requested' => ['awaiting_contract_signature', 'awaiting_branch_review', 'cancelled'],
        'accepted' => [],
        'rejected' => [],
        'cancelled' => [],
    ];
}

function applicationInState(string $state): Application
{
    return match ($state) {
        'awaiting_registration_fee' => Application::factory()->awaitingRegistrationFee()->create(),
        'awaiting_application_completion' => Application::factory()->awaitingApplicationCompletion()->create(),
        'awaiting_contract_signature' => Application::factory()->awaitingContractSignature()->create(),
        'awaiting_branch_review' => Application::factory()->awaitingBranchReview()->create(),
        'correction_requested' => Application::factory()->correctionRequested()->create(),
        'accepted' => Application::factory()->accepted()->create(),
        'rejected' => Application::factory()->rejected()->create(),
        'cancelled' => Application::factory()->cancelled()->create(),
    };
}

// ── Forbidden cells: every blank cell throws and leaves the state untouched ──

it('refuses every forbidden transition without side effects', function (string $from, string $to) {
    $application = applicationInState($from);
    $target = stateClasses()[$to];

    expect($application->status->canTransitionTo($target))->toBeFalse();

    expect(fn () => $application->status->transitionTo($target))
        ->toThrow(TransitionNotFound::class);

    expect($application->fresh()->status::$name)->toBe($from);
})->with(function () {
    $rows = [];
    foreach (allowedEdges() as $from => $allowed) {
        foreach (array_keys(stateClasses()) as $to) {
            if ($to === $from || in_array($to, $allowed, true)) {
                continue;
            }
            $rows["{$from} -> {$to}"] = [$from, $to];
        }
    }

    return $rows;
});

// ── Allowed cells: each ✔ edge succeeds with real prerequisites ──────────────

it('crosses the fee gate with a paid registration-fee payment', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();
    $payment = Payment::factory()->forApplication($application)->paid()->create();

    $result = $application->status->transitionTo(AwaitingApplicationCompletion::class, $payment);

    expect($result->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

it('cancels from the fee gate with a note', function () {
    $application = Application::factory()->awaitingRegistrationFee()->create();

    $application->status->transitionTo(Cancelled::class, 'no longer proceeding');

    expect($application->fresh()->status)->toBeInstanceOf(Cancelled::class);
});

it('generates a contract from data completion', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();

    $result = $application->status->transitionTo(AwaitingContractSignature::class);

    expect($result->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($result->activeContract)->not->toBeNull();
});

it('cancels from data completion with a note', function () {
    $application = Application::factory()->awaitingApplicationCompletion()->create();

    $application->status->transitionTo(Cancelled::class, 'cancelled');

    expect($application->fresh()->status)->toBeInstanceOf(Cancelled::class);
});

it('reopens data entry from signature', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    $result = $application->status->transitionTo(AwaitingApplicationCompletion::class);

    expect($result->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

it('advances a signed contract into branch review', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $application->activeContract->update([
        'status' => Signed::class,
        'signed_at' => now(),
        'file_path' => 'contracts/signed.pdf',
    ]);

    $result = $application->status->transitionTo(AwaitingBranchReview::class);

    expect($result->status)->toBeInstanceOf(AwaitingBranchReview::class);
});

it('cancels from signature with a note', function () {
    $application = Application::factory()->awaitingContractSignature()->create();

    $application->status->transitionTo(Cancelled::class, 'cancelled');

    expect($application->fresh()->status)->toBeInstanceOf(Cancelled::class);
});

it('accepts from branch review with a signed active version', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $result = $application->status->transitionTo(Accepted::class);

    expect($result->status)->toBeInstanceOf(Accepted::class)
        ->and($result->student_id)->not->toBeNull();
});

it('rejects from branch review with a reason', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $result = $application->status->transitionTo(Rejected::class, 'ineligible');

    expect($result->status)->toBeInstanceOf(Rejected::class);
});

it('requests a correction from branch review', function () {
    $this->actingAs(User::factory()->create());
    $application = Application::factory()->awaitingBranchReview()->create();

    $result = app(RequestCorrectionAction::class)->handle($application, auth()->user(), 'fix it', ['fix civil number']);

    expect($result->status)->toBeInstanceOf(CorrectionRequested::class);
});

it('cancels from branch review with a note', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $application->status->transitionTo(Cancelled::class, 'cancelled');

    expect($application->fresh()->status)->toBeInstanceOf(Cancelled::class);
});

it('completes a non-contract-relevant correction back to branch review', function () {
    $this->actingAs(User::factory()->create());
    $application = Application::factory()->awaitingBranchReview()->create();
    app(RequestCorrectionAction::class)->handle($application, auth()->user(), 'fix', ['a']);

    $application->update(['father_phone' => '900000123']);
    $result = app(CompleteCorrectionAction::class)->handle($application->fresh(), auth()->user(), [0]);

    expect($result->status)->toBeInstanceOf(AwaitingBranchReview::class);
});

it('completes a contract-relevant correction into re-signature', function () {
    $this->actingAs(User::factory()->create());
    $application = Application::factory()->awaitingBranchReview()->create();
    app(RequestCorrectionAction::class)->handle($application, auth()->user(), 'fix', ['a']);

    $application->update(['student_name' => 'Changed Name']);
    $result = app(CompleteCorrectionAction::class)->handle($application->fresh(), auth()->user(), [0]);

    expect($result->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('cancels from correction requested with a note', function () {
    $this->actingAs(User::factory()->create());
    $application = Application::factory()->awaitingBranchReview()->create();
    app(RequestCorrectionAction::class)->handle($application, auth()->user(), 'fix', ['a']);

    $application->fresh()->status->transitionTo(Cancelled::class, 'cancelled');

    expect($application->fresh()->status)->toBeInstanceOf(Cancelled::class);
});

it('cancels the active contract version when the application is cancelled', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $contract = $application->activeContract;

    $application->status->transitionTo(Cancelled::class, 'cancelled');
    $contract->refresh();

    expect($contract->status)->toBeInstanceOf(App\States\Contracts\Cancelled::class)
        ->and($contract->token)->toBeNull()
        ->and($application->fresh()->activeContract)->toBeNull();
});

it('records the acting user on a requested-correction activity', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $application = Application::factory()->awaitingBranchReview()->create();

    app(RequestCorrectionAction::class)->handle($application, auth()->user(), 'fix', ['a']);

    $activity = $application->fresh()->activities()
        ->where('to_state', CorrectionRequested::getMorphClass())
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->transitioned_by)->toBe($user->id);
});
