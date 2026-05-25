<?php

use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Source;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'sanctum');
});

it('filters leads by an exact value in the data JSON field', function () {
    Lead::factory()->create(['data' => ['mother_phone' => '111111111']]);
    Lead::factory()->create(['data' => ['mother_phone' => '999999999']]);

    $response = $this->getJson('/api/v1/leads?data[mother_phone]=111111111');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.mother_phone', '111111111')
        ->assertJsonMissingPath('data.0.data.mother_phone');
});

it('returns no results when data JSON filter does not match', function () {
    Lead::factory()->create(['data' => ['mother_phone' => '111111111']]);

    $response = $this->getJson('/api/v1/leads?data[mother_phone]=000000000');

    $response->assertOk()->assertJsonCount(0, 'data');
});

it('searches leads by guardian_name via search param', function () {
    Lead::factory()->create(['guardian_name' => 'Ahmed Ibrahim']);
    Lead::factory()->create(['guardian_name' => 'Mohamed Ali']);

    $response = $this->getJson('/api/v1/leads?search=Ahmed');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.guardian_name', 'Ahmed Ibrahim');
});

it('searches leads inside the data JSON field when search_fields includes data key', function () {
    Lead::factory()->create(['guardian_name' => 'Test User', 'data' => ['mother_phone' => '0501112233']]);
    Lead::factory()->create(['guardian_name' => 'Another User', 'data' => ['mother_phone' => '0509998877']]);

    $response = $this->getJson('/api/v1/leads?search=050111&search_fields[]=data.mother_phone');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.mother_phone', '0501112233')
        ->assertJsonMissingPath('data.0.data.mother_phone');
});

it('combines standard filters with data JSON filter', function () {
    $branch = Branch::factory()->create();

    Lead::factory()->create(['branch_id' => $branch->id, 'data' => ['mother_phone' => '111111111']]);
    Lead::factory()->create(['branch_id' => $branch->id, 'data' => ['mother_phone' => '999999999']]);

    $response = $this->getJson("/api/v1/leads?branch_id={$branch->id}&data[mother_phone]=111111111");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.mother_phone', '111111111')
        ->assertJsonMissingPath('data.0.data.mother_phone');
});

it('stores mother phone on the lead column when provided at the top level', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    Season::factory()->create([
        'type' => $program->type,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/leads', [
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

    $response->assertCreated()
        ->assertJsonPath('mother_phone', '0507654321')
        ->assertJsonPath('data.preferred_time', 'morning')
        ->assertJsonMissingPath('data.mother_phone');
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
