<?php

use App\Models\Branch;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->assertJsonPath('data.0.data.mother_phone', '111111111');
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
        ->assertJsonPath('data.0.data.mother_phone', '0501112233');
});

it('combines standard filters with data JSON filter', function () {
    $branch = Branch::factory()->create();

    Lead::factory()->create(['branch_id' => $branch->id, 'data' => ['mother_phone' => '111111111']]);
    Lead::factory()->create(['branch_id' => $branch->id, 'data' => ['mother_phone' => '999999999']]);

    $response = $this->getJson("/api/v1/leads?branch_id={$branch->id}&data[mother_phone]=111111111");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.data.mother_phone', '111111111');
});
