<?php

use App\Actions\Leads\CreateLeadAction;
use App\Enums\ProgramType;
use App\Enums\Source;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The lead notification only fires when `services.fasih.lead_created.enabled` is true *and* the
 * app is in `production` — forced here rather than relying on APP_ENV=testing to keep the path
 * disabled, so the afterCommit wiring itself is exercised. The HTTP driver + endpoint are set
 * so the adapter actually reaches Laravel's HTTP client rather than the default no-op driver.
 */
function forceProductionWebhooks(): void
{
    app()->instance('env', 'production');
    config([
        'services.fasih.lead_created.enabled' => true,
        'services.fasih.driver' => 'http',
        'services.fasih.lead_created.url' => 'https://fasih.test/lead-created',
    ]);
}

function leadWebhookContext(): array
{
    $branch = Branch::factory()->create();
    $program = Program::factory()->create(['type' => ProgramType::Academic]);
    Season::factory()->academic()->create(['is_active' => true]);

    return [$branch, $program];
}

afterEach(function () {
    app()->instance('env', 'testing');
});

it('sends the webhook only after a successful transaction commits', function () {
    forceProductionWebhooks();
    Http::fake();
    [$branch, $program] = leadWebhookContext();

    DB::transaction(function () use ($branch, $program) {
        app(CreateLeadAction::class)->execute(
            whatsapp: '+96891112222',
            guardian_name: 'Guardian',
            student_name: 'Student',
            program_id: $program->id,
            branch_id: $branch->id,
            source: Source::DASHBOARD->value,
        );

        // Still inside the transaction: the webhook must not have fired yet.
        Http::assertNothingSent();
    });

    Http::assertSentCount(1);
});

it('never sends the webhook when the enclosing transaction rolls back', function () {
    forceProductionWebhooks();
    Http::fake();
    [$branch, $program] = leadWebhookContext();

    try {
        DB::transaction(function () use ($branch, $program) {
            app(CreateLeadAction::class)->execute(
                whatsapp: '+96891113333',
                guardian_name: 'Guardian',
                student_name: 'Student',
                program_id: $program->id,
                branch_id: $branch->id,
                source: Source::DASHBOARD->value,
            );

            throw new RuntimeException('forced rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    Http::assertNothingSent();
});

it('dispatches immediately when CreateLeadAction is called outside of any transaction', function () {
    forceProductionWebhooks();
    Http::fake();
    [$branch, $program] = leadWebhookContext();

    app(CreateLeadAction::class)->execute(
        whatsapp: '+96891114444',
        guardian_name: 'Guardian',
        student_name: 'Student',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    Http::assertSentCount(1);
});

it('does not dispatch when the webhook feature is disabled, regardless of transaction state', function () {
    app()->instance('env', 'production');
    config([
        'services.fasih.lead_created.enabled' => false,
        'services.fasih.driver' => 'http',
        'services.fasih.lead_created.url' => 'https://fasih.test/lead-created',
    ]);
    Http::fake();
    [$branch, $program] = leadWebhookContext();

    app(CreateLeadAction::class)->execute(
        whatsapp: '+96891115555',
        guardian_name: 'Guardian',
        student_name: 'Student',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    Http::assertNothingSent();
});
