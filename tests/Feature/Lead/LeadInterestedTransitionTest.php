<?php

use App\Enums\LeadContactMethod;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\States\Leads\ContactedLead;
use App\States\Leads\Interested;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    expect($lead->status)->toBeInstanceOf(Interested::class);
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
