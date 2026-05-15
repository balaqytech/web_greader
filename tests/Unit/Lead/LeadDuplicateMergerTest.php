<?php

use App\Models\Branch;
use App\Models\Program;
use App\Models\Season;
use App\Support\LeadDuplicateMerger;
use App\Support\LeadIdentityNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('merges leads that share the same identity fingerprint', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    $season = Season::factory()->create();

    if (Schema::hasIndex('leads', 'leads_identity_unique')) {
        Schema::table('leads', function ($table) {
            $table->dropUnique('leads_identity_unique');
        });
    }

    $normalizer = app(LeadIdentityNormalizer::class);

    $fingerprint = $normalizer->fingerprint(
        '+96891111111',
        $program->id,
        $season->id,
        $branch->id,
        'أحمد',
    );

    $normalized = $normalizer->normalizeName('أحمد');

    foreach ([
        ['ref_no' => 'TEST001', 'student_name' => 'أحمد'],
        ['ref_no' => 'TEST002', 'student_name' => 'احمد'],
    ] as $row) {
        DB::table('leads')->insert([
            'ref_no' => $row['ref_no'],
            'whatsapp' => '+96891111111',
            'student_name' => $row['student_name'],
            'student_name_normalized' => $normalized,
            'identity_fingerprint' => $fingerprint,
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
    }

    expect(DB::table('leads')->count())->toBe(2);

    $merged = app(LeadDuplicateMerger::class)->mergeExactFingerprintDuplicates();

    expect($merged)->toBe(1)
        ->and(DB::table('leads')->count())->toBe(1);
});
