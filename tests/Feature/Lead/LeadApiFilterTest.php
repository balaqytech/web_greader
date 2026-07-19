<?php

use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Source;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * The lead lookup endpoint is now an exact-whatsapp query returning the minimal
 * LeadSummaryResource (see tests/Feature/Api). The scope-level filter/search behavior these
 * tests still cover is exercised directly against the model, where it remains in use.
 */

it('filters leads by an exact value in the data JSON field via the model scope', function () {
    Lead::factory()->create(['data' => ['preferred_time' => 'morning']]);
    Lead::factory()->create(['data' => ['preferred_time' => 'evening']]);

    $results = Lead::query()->filter(['data.preferred_time' => 'morning'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->data['preferred_time'])->toBe('morning');
});

it('filters leads by the mother_phone column through the data scope alias', function () {
    Lead::factory()->create(['mother_phone' => '111111111']);
    Lead::factory()->create(['mother_phone' => '999999999']);

    $results = Lead::query()->filter(['data.mother_phone' => '111111111'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->mother_phone)->toBe('111111111');
});

it('searches leads by guardian_name via the model scope', function () {
    Lead::factory()->create(['guardian_name' => 'Ahmed Ibrahim']);
    Lead::factory()->create(['guardian_name' => 'Mohamed Ali']);

    $results = Lead::query()->search('Ahmed')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->guardian_name)->toBe('Ahmed Ibrahim');
});

it('searches leads inside the mother_phone column when the search field is included', function () {
    Lead::factory()->create(['guardian_name' => 'Test User', 'mother_phone' => '0501112233']);
    Lead::factory()->create(['guardian_name' => 'Another User', 'mother_phone' => '0509998877']);

    $results = Lead::query()->search('050111', ['mother_phone'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->mother_phone)->toBe('0501112233');
});

it('stores the mother phone on the lead column and returns the minimal summary resource', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    Season::factory()->create([
        'type' => $program->type,
        'is_active' => true,
    ]);

    [, $token] = fasihServiceToken();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/leads', [
            'whatsapp' => '0501234567',
            'guardian_name' => 'Guardian',
            'student_name' => 'Student',
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'source' => Source::WEBSITE->value,
            'mother_phone' => '0507654321',
            'data' => [
                'preferred_time' => 'morning',
            ],
        ]);

    $response->assertCreated();

    // The response is the minimal allowlist — never guardian PII, phones, or the data bag.
    expect(array_keys($response->json('data')))->toEqualCanonicalizing([
        'ref_no', 'student_name', 'status', 'status_label', 'branch_name', 'program_name', 'created_at',
    ]);

    $lead = Lead::withoutGlobalScopes()->where('student_name', 'Student')->firstOrFail();

    expect($lead->mother_phone)->toBe(normalize_phone_number('0507654321'))
        ->and($lead->whatsapp)->toBe(normalize_phone_number('0501234567'))
        ->and($lead->data)->toBe(['preferred_time' => 'morning']);
});

it('backfills mother phone from legacy lead data', function () {
    $lead = Lead::factory()->create([
        'mother_phone' => null,
        'data' => [
            'mother_phone' => '0501112233',
            'preferred_time' => 'evening',
        ],
    ]);

    DB::table('leads')->where('id', $lead->id)->update([
        'mother_phone' => null,
        'data' => json_encode([
            'mother_phone' => '0501112233',
            'preferred_time' => 'evening',
        ]),
    ]);

    $migration = require database_path('migrations/2026_05_24_184528_backfill_lead_mother_phone_from_data.php');
    $migration->up();

    $lead->refresh();

    expect($lead->mother_phone)->toBe('0501112233')
        ->and($lead->data)->toBe(['preferred_time' => 'evening']);
});

it('prefills application data from the lead mother phone column', function () {
    $lead = Lead::factory()->create([
        'mother_phone' => '0501112233',
        'data' => [
            'preferred_time' => 'evening',
        ],
    ]);

    $dto = CreateApplicationDTO::fromLead($lead);

    expect($dto->mother_phone)->toBe('0501112233');
});
