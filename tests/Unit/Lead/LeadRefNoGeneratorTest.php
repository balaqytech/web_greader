<?php

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\Support\LeadIdentityNormalizer;
use App\Support\LeadRefNoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates the next sequence after the highest ref_no for today', function () {
    $this->travelTo('2026-05-15 12:00:00');

    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    $season = Season::factory()->create();
    $normalizer = app(LeadIdentityNormalizer::class);

    DB::table('leads')->insert([
        'ref_no' => '20260515000499',
        'whatsapp' => '+96890000001',
        'student_name' => 'Existing',
        'student_name_normalized' => $normalizer->normalizeName('Existing'),
        'identity_fingerprint' => $normalizer->fingerprint(
            '+96890000001',
            $program->id,
            $season->id,
            $branch->id,
            'Existing',
        ),
        'guardian_name' => 'Guardian',
        'program_id' => $program->id,
        'season_id' => $season->id,
        'branch_id' => $branch->id,
        'program_type' => 'academic',
        'status' => 'new',
        'source' => 'dashboard',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(app(LeadRefNoGenerator::class)->generate())->toBe('20260515000500');
});

it('assigns a non-colliding ref_no when creating a lead', function () {
    $this->travelTo('2026-05-15 12:00:00');

    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    $season = Season::factory()->create();
    $normalizer = app(LeadIdentityNormalizer::class);

    DB::table('leads')->insert([
        'ref_no' => '20260515000499',
        'whatsapp' => '+96890000001',
        'student_name' => 'Existing',
        'student_name_normalized' => $normalizer->normalizeName('Existing'),
        'identity_fingerprint' => $normalizer->fingerprint(
            '+96890000001',
            $program->id,
            $season->id,
            $branch->id,
            'Existing',
        ),
        'guardian_name' => 'Guardian',
        'program_id' => $program->id,
        'season_id' => $season->id,
        'branch_id' => $branch->id,
        'program_type' => 'academic',
        'status' => 'new',
        'source' => 'dashboard',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lead = Lead::withoutGlobalScopes()->create([
        'whatsapp' => '+96890000002',
        'student_name' => 'New Lead',
        'guardian_name' => 'Guardian',
        'program_id' => $program->id,
        'season_id' => $season->id,
        'branch_id' => $branch->id,
        'program_type' => 'academic',
        'status' => 'new',
        'source' => 'dashboard',
    ]);

    expect($lead->ref_no)->toBe('20260515000500');
});
