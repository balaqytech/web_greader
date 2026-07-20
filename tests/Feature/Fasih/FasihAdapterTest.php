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
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

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
    Http::fake();

    app(FasihClient::class)->leadCreated(['a' => 1]);
    app(FasihClient::class)->affiliateVerified(['a' => 1]);

    Http::assertNothingSent();
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
        'connect_timeout' => 3,
        'lead_created' => ['url' => 'https://fasih.test/lead'],
    ]);

    Http::fake();
    $client->leadCreated(['ref_no' => 'L-1']);

    $expectedSignature = hash_hmac('sha256', json_encode(['ref_no' => 'L-1']), 'top-secret');

    Http::assertSent(function (Request $request) use ($expectedSignature): bool {
        return $request->url() === 'https://fasih.test/lead'
            && $request->body() === json_encode(['ref_no' => 'L-1'])
            && $request->data() === ['ref_no' => 'L-1']
            && $request->hasHeader(signatureHeaderName(), $expectedSignature);
    });
});

it('does not sign the lead-created notification when no secret is configured', function () {
    $client = new HttpFasihClient([
        'secret' => null,
        'lead_created' => ['url' => 'https://fasih.test/lead'],
    ]);

    Http::fake();
    $client->leadCreated(['ref_no' => 'L-1']);

    Http::assertSent(function (Request $request): bool {
        return ! $request->hasHeader(signatureHeaderName());
    });
});

it('never signs the affiliate-verified notification', function () {
    $client = new HttpFasihClient([
        'secret' => 'top-secret',
        'affiliate_verified' => ['url' => 'https://fasih.test/affiliate'],
    ]);

    Http::fake();
    $client->affiliateVerified(['code' => 'AFF-1']);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://fasih.test/affiliate'
            && $request->data() === ['code' => 'AFF-1']
            && ! $request->hasHeader(signatureHeaderName());
    });
});

it('sends nothing when the endpoint url is missing', function () {
    $client = new HttpFasihClient(['secret' => 's', 'lead_created' => ['url' => null]]);

    Http::fake();
    $client->leadCreated(['ref_no' => 'L-1']);

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Failure reporting / rollback suppression
|--------------------------------------------------------------------------
*/

it('reports a real HTTP adapter failure after commit without failing the domain operation', function () {
    Exceptions::fake();
    Http::fake([
        'https://fasih.test/lead' => Http::response(['error' => 'down'], 503),
    ]);

    app()->instance('env', 'production');
    config([
        'services.fasih.driver' => 'http',
        'services.fasih.lead_created.enabled' => true,
        'services.fasih.lead_created.url' => 'https://fasih.test/lead',
    ]);
    app()->forgetInstance(FasihManager::class);

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

    Exceptions::assertReported(RequestException::class);
    Http::assertSentCount(3);

    app()->instance('env', 'testing');
});
