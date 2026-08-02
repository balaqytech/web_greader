<?php

use App\Actions\Leads\TransitionLeadStateAction;
use App\Enums\LeadContactMethod;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Services\Payments\Drivers\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Leads\ContactedLead;
use App\States\Leads\Interested;
use App\States\Leads\NewLead;
use App\Support\Api\FasihServiceAbilities;
use App\Support\Money\OmrAmount;
use App\Support\Settings\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transitions a lead through the Filament transition action', function () {
    $lead = Lead::factory()->create();

    expect($lead->status)->toBeInstanceOf(NewLead::class);

    app(TransitionLeadStateAction::class)->execute(
        ContactedLead::class,
        $lead,
        'Test User',
        LeadContactMethod::Call,
        'Reached the guardian',
    );

    $lead->refresh();

    expect($lead->status)->toBeInstanceOf(ContactedLead::class)
        ->and($lead->contacts)->toHaveCount(1)
        ->and($lead->contacts->first()->contacted_by)->toBe('Test User')
        ->and($lead->contacts->first()->contact_method)->toBe(LeadContactMethod::Call)
        ->and($lead->contacts->first()->notes)->toBe('Reached the guardian');
});

it('allows transitioning to interested when the program is available in the lead branch', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    $program->branches()->attach($branch, ['price' => 0]);

    $lead = Lead::factory()->contactedLead()->create([
        'branch_id' => $branch->id,
        'program_id' => $program->id,
    ]);

    expect($lead->status)->toBeInstanceOf(ContactedLead::class);

    $lead->status->transitionTo(
        Interested::class,
        contactedBy: 'Test User',
        contactMethod: LeadContactMethod::Call,
    );

    $lead->refresh();

    expect($lead->status)->toBeInstanceOf(Interested::class)
        ->and($lead->application)->not->toBeNull()
        ->and($lead->application->status)->toBeInstanceOf(AwaitingRegistrationFee::class)
        ->and($lead->application->student_name)->toBe($lead->student_name)
        ->and($lead->application->ref_no)->toStartWith('APP-');
});

it('preserves the lead guardian for status lookup and payment initiation', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    $program->branches()->attach($branch, ['price' => 0]);

    $lead = Lead::factory()->contactedLead()->create([
        'branch_id' => $branch->id,
        'program_id' => $program->id,
        'guardian_name' => 'Test Guardian',
        'whatsapp' => '99123456',
    ]);

    $lead->status->transitionTo(
        Interested::class,
        contactedBy: 'Test User',
        contactMethod: LeadContactMethod::Call,
    );

    $application = $lead->fresh()->application;

    expect($application->father_name)->toBe($lead->guardian_name)
        ->and($application->father_phone)->toBe($lead->whatsapp)
        ->and($application->father_is_guardian)->toBeTrue()
        ->and($application->guardian_name)->toBe($lead->guardian_name)
        ->and($application->guardian_phone)->toBe($lead->whatsapp);

    app(PaymentSettings::class)->setRegistrationFee(OmrAmount::fromString('25.000'));
    app()->instance(PaymentGateway::class, new FakePaymentGateway);

    [, $token] = fasihServiceToken([
        FasihServiceAbilities::ApplicationsStatus,
        FasihServiceAbilities::PaymentsInitiate,
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/applications/status-check', [
            'application_reference' => $application->ref_no,
            'guardian_phone' => $lead->whatsapp,
        ])
        ->assertOk()
        ->assertJsonPath('data.application_reference', $application->ref_no);

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'converted-lead-payment')
        ->postJson('/api/v1/payments/thawani', [
            'application_reference' => $application->ref_no,
            'guardian_phone' => $lead->whatsapp,
        ])
        ->assertCreated()
        ->assertJsonPath('data.method', 'thawani');
});

it('blocks transitioning to interested when the program is not available in the lead branch', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    // Intentionally NOT attaching the program to the branch

    $lead = Lead::factory()->contactedLead()->create([
        'branch_id' => $branch->id,
        'program_id' => $program->id,
    ]);

    expect($lead->status)->toBeInstanceOf(ContactedLead::class);

    expect(fn () => $lead->status->transitionTo(
        Interested::class,
        contactedBy: 'Test User',
        contactMethod: LeadContactMethod::Call,
    ))->toThrow(ProgramNotAvailableInBranchException::class);

    $lead->refresh();

    // Ensure the lead status was not changed
    expect($lead->status)->toBeInstanceOf(ContactedLead::class);
});

it('does not create a contact record when the transition is blocked', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();

    $lead = Lead::factory()->contactedLead()->create([
        'branch_id' => $branch->id,
        'program_id' => $program->id,
    ]);

    try {
        $lead->status->transitionTo(
            Interested::class,
            contactedBy: 'Test User',
            contactMethod: LeadContactMethod::Call,
        );
    } catch (ProgramNotAvailableInBranchException) {
        // expected
    }

    expect($lead->contacts()->count())->toBe(0);
});
