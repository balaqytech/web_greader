<?php

use App\Models\Application;
use Illuminate\Support\Facades\DB;

function statusMigration(): object
{
    return require database_path('migrations/2026_07_13_100002_migrate_application_status_values_to_target_states.php');
}

function seedApplicationWithRawStatus(string $status): int
{
    $app = Application::factory()->create();
    DB::table('applications')->where('id', $app->id)->update(['status' => $status]);

    return $app->id;
}

function rawStatus(int $id): string
{
    return DB::table('applications')->where('id', $id)->value('status');
}

it('maps every legacy status to its target state on up()', function () {
    $ids = [
        'draft' => seedApplicationWithRawStatus('draft'),
        'submitted' => seedApplicationWithRawStatus('submitted'),
        'waiting_contract_signature' => seedApplicationWithRawStatus('waiting_contract_signature'),
        'under_review' => seedApplicationWithRawStatus('under_review'),
    ];

    statusMigration()->up();

    expect(rawStatus($ids['draft']))->toBe('awaiting_registration_fee')
        ->and(rawStatus($ids['submitted']))->toBe('awaiting_application_completion')
        ->and(rawStatus($ids['waiting_contract_signature']))->toBe('awaiting_contract_signature')
        ->and(rawStatus($ids['under_review']))->toBe('awaiting_branch_review');
});

it('leaves terminal states unchanged on up()', function () {
    $accepted = seedApplicationWithRawStatus('accepted');
    $rejected = seedApplicationWithRawStatus('rejected');
    $cancelled = seedApplicationWithRawStatus('cancelled');

    statusMigration()->up();

    expect(rawStatus($accepted))->toBe('accepted')
        ->and(rawStatus($rejected))->toBe('rejected')
        ->and(rawStatus($cancelled))->toBe('cancelled');
});

it('reverses the mapping on down()', function () {
    $id = seedApplicationWithRawStatus('awaiting_branch_review');

    statusMigration()->down();

    expect(rawStatus($id))->toBe('under_review');
});

it('does not rewrite historical activity state values', function () {
    $app = Application::factory()->create();
    $activityId = DB::table('application_activities')->insertGetId([
        'application_id' => $app->id,
        'transitioned_by' => null,
        'from_state' => 'draft',
        'to_state' => 'submitted',
        'notes' => null,
        'transitioned_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    statusMigration()->up();

    $activity = DB::table('application_activities')->where('id', $activityId)->first();

    expect($activity->from_state)->toBe('draft')
        ->and($activity->to_state)->toBe('submitted');
});
