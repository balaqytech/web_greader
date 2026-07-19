<?php

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Actions\Applications\SignContractOnlineAction;
use App\Actions\Applications\UploadSignedContractAction;
use App\Actions\Support\CreatePdfAction;
use App\Events\ApplicationAccepted;
use App\Events\ApplicationRejected;
use App\Events\ContractGenerated;
use App\Events\ContractSigned;
use App\Events\CorrectionRequested as CorrectionRequestedEvent;
use App\Events\PaymentPaid as PaymentPaidEvent;
use App\Models\Application;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Models\User;
use App\States\Applications\Accepted;
use App\States\Applications\CorrectionRequested;
use App\States\Applications\Rejected;
use App\States\Payments\Paid;
use App\Support\Payments\Evidence\CashSettlementEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function outboxOf(string $eventType)
{
    return OutboxMessage::where('event_type', $eventType);
}

function signatureImage(): string
{
    // A genuine 1x1 PNG data URI (not merely bytes behind a matching prefix).
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
}

/*
|--------------------------------------------------------------------------
| Listener + payload shape (every event)
|--------------------------------------------------------------------------
*/

it('records each domain event as a pending outbox row', function (object $event, string $type, string $aggregateType) {
    event($event);

    $row = OutboxMessage::latest('id')->firstOrFail();

    expect($row->event_type)->toBe($type)
        ->and($row->aggregate_type)->toBe($aggregateType)
        ->and($row->status)->toBe(OutboxMessage::StatusPending)
        ->and($row->attempts)->toBe(0)
        ->and($row->processed_at)->toBeNull();
})->with([
    'accepted' => [fn () => new ApplicationAccepted(1, 'APP-1', 3), 'application.accepted', 'application'],
    'rejected' => [fn () => new ApplicationRejected(1, 'APP-1', 3), 'application.rejected', 'application'],
    'correction' => [fn () => new CorrectionRequestedEvent(1, 'APP-1', 3), 'application.correction_requested', 'application'],
    'payment paid' => [fn () => new PaymentPaidEvent(9, 'PAY-1', 1, 'APP-1', 3, 'cash', '25.000'), 'payment.paid', 'payment'],
    'contract generated' => [fn () => new ContractGenerated(5, 1, 'APP-1', 3, 2), 'contract.generated', 'contract'],
    'contract signed' => [fn () => new ContractSigned(5, 1, 'APP-1', 3, 2, true), 'contract.signed', 'contract'],
]);

it('keeps event payloads free of PII, free text, tokens, and artifact paths', function () {
    event(new ApplicationRejected(1, 'APP-1', 3));
    event(new PaymentPaidEvent(9, 'PAY-1', 1, 'APP-1', 3, 'cash', '25.000'));
    event(new ContractSigned(5, 1, 'APP-1', 3, 2, false));

    $payloads = OutboxMessage::pluck('payload')->map(fn ($p) => json_encode($p))->implode(' ');

    foreach (['reason', 'rejection', 'token', 'rendered_body', 'file_path', 'signature', 'provider_payload'] as $forbidden) {
        expect($payloads)->not->toContain($forbidden);
    }
});

/*
|--------------------------------------------------------------------------
| Transactional guarantee
|--------------------------------------------------------------------------
*/

it('rolls the outbox insert back with the enclosing transaction', function () {
    try {
        DB::transaction(function () {
            event(new ApplicationAccepted(1, 'APP-1', 3));

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(OutboxMessage::count())->toBe(0);
});

it('does not deliver or process recorded events (no consumer in phase 5)', function () {
    $application = Application::factory()->awaitingBranchReview()->create();
    $application->status->transitionTo(Accepted::class);

    // The row is written but nothing advances it — it stays pending, unprocessed.
    expect(outboxOf('application.accepted')->where('status', OutboxMessage::StatusPending)->count())->toBe(1)
        ->and(OutboxMessage::whereNotNull('processed_at')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Integration: real dispatch points
|--------------------------------------------------------------------------
*/

it('records application.accepted when an application is accepted', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $application->status->transitionTo(Accepted::class);

    $row = outboxOf('application.accepted')->where('aggregate_id', (string) $application->id)->firstOrFail();
    expect($row->payload['reference'])->toBe($application->ref_no);
});

it('records application.rejected without leaking the rejection reason', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $application->status->transitionTo(Rejected::class, 'Sensitive private rejection detail');

    $row = outboxOf('application.rejected')->where('aggregate_id', (string) $application->id)->firstOrFail();
    expect(json_encode($row->payload))->not->toContain('Sensitive private rejection detail');
});

it('records application.correction_requested', function () {
    $application = Application::factory()->awaitingBranchReview()->create();
    $actor = User::factory()->create();

    $application->status->transitionTo(CorrectionRequested::class, $actor, 'Fix the guardian id', ['Fix guardian id number']);

    expect(outboxOf('application.correction_requested')->where('aggregate_id', (string) $application->id)->exists())->toBeTrue();
});

it('records contract.generated when a contract version is generated', function () {
    $application = Application::factory()->awaitingContractSignature()->create();
    $before = outboxOf('contract.generated')->count();

    $contract = app(GenerateApplicationContractAction::class)->handle($application);

    expect(outboxOf('contract.generated')->count())->toBe($before + 1)
        ->and(outboxOf('contract.generated')->where('aggregate_id', (string) $contract->id)->exists())->toBeTrue();
});

it('records contract.signed for both the online and staff-upload signing paths', function () {
    Storage::fake('public');
    app()->bind(CreatePdfAction::class, fn () => new class
    {
        public function execute(string $view, string $path, array $data): string
        {
            Storage::disk('public')->put($path, 'pdf-bytes');

            return Storage::url($path);
        }
    });

    // Online applicant signing.
    $online = Application::factory()->awaitingContractSignature()->create();
    app(SignContractOnlineAction::class)->execute($online->activeContract, $online->activeContract->token, signatureImage());

    $onlineRow = outboxOf('contract.signed')->where('aggregate_id', (string) $online->activeContract->id)->firstOrFail();
    expect($onlineRow->payload['signed_by_applicant'])->toBeTrue();

    // Staff upload signing.
    $uploaded = Application::factory()->awaitingContractSignature()->create();
    Storage::disk('public')->put('contracts/uploads/signed.pdf', 'signed');
    app(UploadSignedContractAction::class)->execute($uploaded, 'contracts/uploads/signed.pdf');

    $uploadRow = outboxOf('contract.signed')->where('aggregate_id', (string) $uploaded->activeContract->id)->firstOrFail();
    expect($uploadRow->payload['signed_by_applicant'])->toBeFalse();
});

it('records payment.paid only for the paid winner, never for a double-charge loser', function () {
    // Winner: a clean cash settlement.
    $winnerPayment = Payment::factory()->cash()->pending()->create();
    $winnerPayment->status->transitionTo(Paid::class, new CashSettlementEvidence(
        confirmedBy: User::factory()->create(),
        reference: 'CASH-1',
        notes: 'front desk',
    ));

    expect(outboxOf('payment.paid')->where('aggregate_id', (string) $winnerPayment->id)->exists())->toBeTrue();

    // Loser: a second charge on an application already paid → moves to Failed, emits nothing.
    $application = Application::factory()->awaitingRegistrationFee()->create();
    Payment::factory()->forApplication($application)->cash()->paid()->create();
    $loser = Payment::factory()->forApplication($application)->cash()->pending()->create();

    $result = $loser->status->transitionTo(Paid::class, new CashSettlementEvidence(
        confirmedBy: User::factory()->create(),
        reference: 'CASH-2',
        notes: 'front desk',
    ));

    expect($result->status)->not->toBeInstanceOf(Paid::class)
        ->and(outboxOf('payment.paid')->where('aggregate_id', (string) $loser->id)->exists())->toBeFalse();
});
