<?php

use App\Models\Branch;
use App\Models\Lead;

it('rejects an unauthenticated GET to the lead lookup with 401 and exposes no lead PII', function () {
    $lead = Lead::factory()->create([
        'guardian_name' => 'Guardian Should Not Leak',
        'student_name' => 'Student Should Not Leak',
        'whatsapp' => '+96899123456',
    ]);

    $response = $this->getJson('/api/v1/leads?whatsapp=+96899123456');

    $response->assertUnauthorized();

    $response->assertDontSee($lead->guardian_name, escape: false)
        ->assertDontSee($lead->student_name, escape: false)
        ->assertDontSee($lead->whatsapp, escape: false);
});

it('lets the Fasih service account look a lead up by its exact normalized whatsapp', function () {
    $branch = Branch::factory()->create(['name' => 'Muscat Branch']);
    [, $token] = fasihServiceToken();

    $lead = Lead::factory()->create([
        'branch_id' => $branch->id,
        'whatsapp' => '+96899123456',
    ]);

    // A local, unnormalized format resolves to the same stored number.
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads?whatsapp=099123456');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ref_no', $lead->ref_no)
        ->assertJsonPath('data.0.branch_name', 'Muscat Branch');
});

it('returns only the minimal allowlist and never guardian PII, phones, ids, or the data bag', function () {
    $branch = Branch::factory()->create();
    [, $token] = fasihServiceToken();

    $lead = Lead::factory()->create([
        'branch_id' => $branch->id,
        'whatsapp' => '+96899123456',
        'guardian_name' => 'Guardian Should Not Leak',
        'mother_phone' => '+96899000111',
        'data' => ['secret_note' => 'should-not-leak'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads?whatsapp=099123456');

    $response->assertOk();

    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing([
        'ref_no', 'student_name', 'status', 'status_label', 'branch_name', 'program_name', 'created_at',
    ]);

    // The allowlist assertion above already proves no `id` field is emitted; asserting on the
    // integer value itself would be a false positive (it appears inside timestamps).
    $response->assertDontSee($lead->guardian_name, escape: false)
        ->assertDontSee('+96899123456', escape: false)
        ->assertDontSee('+96899000111', escape: false)
        ->assertDontSee('should-not-leak', escape: false);
});

it('requires the whatsapp query parameter', function () {
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads')
        ->assertStatus(422);
});

it('returns 404 for the removed lead transition route', function () {
    $lead = Lead::factory()->create();

    $response = $this->postJson("/api/v1/leads/{$lead->id}/transition");

    $response->assertNotFound();
});
