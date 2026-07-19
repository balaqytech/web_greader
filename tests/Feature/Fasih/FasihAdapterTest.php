<?php

use App\Actions\Leads\CreateLeadAction;
use App\Enums\ProgramType;
use App\Enums\Source;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\Services\Fasih\Drivers\HttpFasihClient;
use App\Services\Fasih\Drivers\NullFasihClient;
use App\Services\Fasih\FasihClient;
use App\Services\Fasih\FasihManager;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookServer\CallWebhookJob;

function signatureHeaderName(): string
{
    return config('webhook-server.signature_header_name', 'Signature');
}

/*
|--------------------------------------------------------------------------
| Driver resolution + extension
|--------------------------------------------------------------------------
*/

it('defaults to the null driver', function () {
    $manager = app(FasihManager::class);

    expect($manager->getDefaultDriver())->toBe('null')
        ->and($manager->driver())->toBeInstanceOf(NullFasihClient::class);
});

it('resolves the http driver when configured', function () {
    config(['services.fasih.driver' => 'http']);

    expect(app(FasihManager::class)->driver())->toBeInstanceOf(HttpFasihClient::class);
});

it('allows a custom driver to be registered via extend', function () {
    $manager = app(FasihManager::class);
    $custom = new NullFasihClient;
    $manager->extend('custom', fn () => $custom);

    expect($manager->driver('custom'))->toBe($custom);
});

it('is a no-op through the null driver', function () {
    Queue::fake();

    app(FasihClient::class)->leadCreated(['a' => 1]);
    app(FasihClient::class)->affiliateVerified(['a' => 1]);

    Queue::assertNothingPushed();
});

/*
|--------------------------------------------------------------------------
| HTTP driver: endpoint, signature, payload
|--------------------------------------------------------------------------
*/

it('signs the lead-created notification when a secret is configured', function () {
    $client = new HttpFasihClient([
        'secret' => 'top-secret',
        'timeout' => 7,
        'lead_created' => ['url' => 'https://fasih.test/lead'],
    ]);

    Queue::fake();
    $client->leadCreated(['ref_no' => 'L-1']);

    Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) {
        return $job->webhookUrl === 'https://fasih.test/lead'
            && $job->payload === ['ref_no' => 'L-1']
            && $job->requestTimeout === 7
            && array_key_exists(signatureHeaderName(), $job->headers);
    });
});

it('does not sign the lead-created notification when no secret is configured', function () {
    $client = new HttpFasihClient([
        'secret' => null,
        'lead_created' => ['url' => 'https://fasih.test/lead'],
    ]);

    Queue::fake();
    $client->leadCreated(['ref_no' => 'L-1']);

    Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) {
        return ! array_key_exists(signatureHeaderName(), $job->headers);
    });
});

it('never signs the affiliate-verified notification', function () {
    $client = new HttpFasihClient([
        'secret' => 'top-secret',
        'affiliate_verified' => ['url' => 'https://fasih.test/affiliate'],
    ]);

    Queue::fake();
    $client->affiliateVerified(['code' => 'AFF-1']);

    Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) {
        return $job->webhookUrl === 'https://fasih.test/affiliate'
            && ! array_key_exists(signatureHeaderName(), $job->headers);
    });
});

it('sends nothing when the endpoint url is missing', function () {
    $client = new HttpFasihClient(['secret' => 's', 'lead_created' => ['url' => null]]);

    Queue::fake();
    $client->leadCreated(['ref_no' => 'L-1']);

    Queue::assertNothingPushed();
});

/*
|--------------------------------------------------------------------------
| Failure reporting / rollback suppression
|--------------------------------------------------------------------------
*/

it('reports an adapter failure after commit without failing the domain operation', function () {
    Exceptions::fake();

    app()->instance('env', 'production');
    config(['services.fasih.lead_created.enabled' => true]);

    // A driver that always throws.
    app()->bind(FasihClient::class, fn () => new class implements FasihClient
    {
        public function leadCreated(array $payload): void
        {
            throw new RuntimeException('fasih outage');
        }

        public function affiliateVerified(array $payload): void {}
    });

    $branch = Branch::factory()->create();
    $program = Program::factory()->create(['type' => ProgramType::Academic]);
    Season::factory()->academic()->create(['is_active' => true]);

    $lead = app(CreateLeadAction::class)->execute(
        whatsapp: '+96891119999',
        guardian_name: 'Guardian',
        student_name: 'Student',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
    );

    // The lead was committed and returned despite the notification failure.
    expect(Lead::withoutGlobalScopes()->whereKey($lead->id)->exists())->toBeTrue();

    Exceptions::assertReported(RuntimeException::class);

    app()->instance('env', 'testing');
});
