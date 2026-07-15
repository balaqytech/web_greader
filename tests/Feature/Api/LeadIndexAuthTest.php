<?php

use App\Models\Branch;
use App\Models\Lead;
use App\Models\User;

it('rejects an unauthenticated GET to the lead index with 401 and exposes no lead PII', function () {
    $lead = Lead::factory()->create([
        'guardian_name' => 'Guardian Should Not Leak',
        'student_name' => 'Student Should Not Leak',
        'whatsapp' => '+96899123456',
    ]);

    $response = $this->getJson('/api/v1/leads');

    $response->assertUnauthorized();

    $response->assertDontSee($lead->guardian_name, escape: false)
        ->assertDontSee($lead->student_name, escape: false)
        ->assertDontSee($lead->whatsapp, escape: false);
});

it('allows a real Sanctum bearer token to access the lead index and receive the existing LeadResource response', function () {
    // BranchScope now restricts a branch-scoped user to their own branch, so the bearer
    // user must belong to the same branch as the lead it expects to see.
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $token = $user->createToken('test-token')->plainTextToken;

    $lead = Lead::factory()->create(['branch_id' => $branch->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $lead->id)
        ->assertJsonPath('data.0.guardian_name', $lead->guardian_name)
        ->assertJsonPath('data.0.student_name', $lead->student_name);
});

it('returns 404 for the removed lead transition route', function () {
    $lead = Lead::factory()->create();

    $response = $this->postJson("/api/v1/leads/{$lead->id}/transition");

    $response->assertNotFound();
});
