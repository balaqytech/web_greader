<?php

use App\Actions\Leads\CreateLeadAction;
use App\Enums\ProgramType;
use App\Enums\Source;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;

function createLeadContext(): array
{
    $branch = Branch::factory()->create();
    $program = Program::factory()->create(['type' => ProgramType::Academic]);
    Season::factory()->academic()->create(['is_active' => true]);

    return compact('branch', 'program');
}

it('merges leads with arabic spelling variants via CreateLeadAction', function () {
    ['branch' => $branch, 'program' => $program] = createLeadContext();

    $action = app(CreateLeadAction::class);
    $whatsapp = '+96891111111';

    $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'أحمد محمد عبدالله',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'احمد محمد عبدالله',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    expect(Lead::withoutGlobalScopes()->count())->toBe(1);
});

it('merges leads when the incoming name is a longer token-prefix match', function () {
    ['branch' => $branch, 'program' => $program] = createLeadContext();

    $action = app(CreateLeadAction::class);
    $whatsapp = '+96892222222';

    $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'احمد محمد عبدالله',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    $lead = $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'احمد محمد عبدالله الهادي',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    expect(Lead::withoutGlobalScopes()->count())->toBe(1)
        ->and($lead->student_name)->toBe('احمد محمد عبدالله الهادي');
});

it('keeps separate leads for siblings on the same whatsapp', function () {
    ['branch' => $branch, 'program' => $program] = createLeadContext();

    $action = app(CreateLeadAction::class);
    $whatsapp = '+96893333333';

    $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'أحمد',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'محمد',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    expect(Lead::withoutGlobalScopes()->count())->toBe(2);
});

it('does not merge single-token names with longer names', function () {
    ['branch' => $branch, 'program' => $program] = createLeadContext();

    $action = app(CreateLeadAction::class);
    $whatsapp = '+96894444444';

    $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'أحمد',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    $action->execute(
        whatsapp: $whatsapp,
        guardian_name: 'ولي الأمر',
        student_name: 'أحمد محمد عبدالله',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    expect(Lead::withoutGlobalScopes()->count())->toBe(2);
});

it('deduplicates via the service API', function () {
    ['branch' => $branch, 'program' => $program] = createLeadContext();
    [, $token] = fasihServiceToken();

    $payload = [
        'whatsapp' => '0501234567',
        'guardian_name' => 'Guardian',
        'student_name' => 'أحمد',
        'program_id' => $program->id,
        'branch_id' => $branch->id,
        'source' => Source::WEBSITE->value,
    ];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/leads', $payload)->assertCreated();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/leads', [
            ...$payload,
            'student_name' => 'احمد',
        ])->assertSuccessful();

    expect(Lead::withoutGlobalScopes()->count())->toBe(1);
});

it('stores normalized identity columns on create', function () {
    ['branch' => $branch, 'program' => $program] = createLeadContext();

    $lead = app(CreateLeadAction::class)->execute(
        whatsapp: '+96895555555',
        guardian_name: 'Guardian',
        student_name: 'أحمد',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    expect($lead->student_name_normalized)->toBe('احمد')
        ->and($lead->identity_fingerprint)->toHaveLength(64);
});
