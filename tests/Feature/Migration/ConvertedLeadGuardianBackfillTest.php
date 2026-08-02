<?php

use App\Models\Application;
use App\Models\Lead;

it('repairs only converted applications whose father contact matches the linked lead guardian', function () {
    $affectedLead = Lead::factory()->create([
        'guardian_name' => 'Affected Guardian',
        'whatsapp' => '99123456',
    ]);
    $unaffectedLead = Lead::factory()->create([
        'guardian_name' => 'Relative Guardian Lead',
        'whatsapp' => '99234567',
    ]);

    $affected = Application::create([
        'lead_id' => $affectedLead->id,
        'season_id' => $affectedLead->season_id,
        'program_id' => $affectedLead->program_id,
        'branch_id' => $affectedLead->branch_id,
        'source' => $affectedLead->source,
        'student_name' => $affectedLead->student_name,
        'father_name' => $affectedLead->guardian_name,
        'father_phone' => $affectedLead->whatsapp,
        'father_is_guardian' => false,
        'mother_is_guardian' => false,
    ]);

    $relativeGuardian = Application::create([
        'lead_id' => $unaffectedLead->id,
        'season_id' => $unaffectedLead->season_id,
        'program_id' => $unaffectedLead->program_id,
        'branch_id' => $unaffectedLead->branch_id,
        'source' => $unaffectedLead->source,
        'student_name' => $unaffectedLead->student_name,
        'father_name' => $unaffectedLead->guardian_name,
        'father_phone' => $unaffectedLead->whatsapp,
        'father_is_guardian' => false,
        'mother_is_guardian' => false,
        'relative_name' => 'Selected Relative',
        'relative_phone' => '99345678',
    ]);

    $migration = require database_path('migrations/2026_07_23_181445_backfill_father_guardian_flag_for_converted_leads.php');
    $migration->up();

    expect($affected->fresh()->father_is_guardian)->toBeTrue()
        ->and($affected->fresh()->guardian_phone)->toBe($affectedLead->whatsapp)
        ->and($relativeGuardian->fresh()->father_is_guardian)->toBeFalse()
        ->and($relativeGuardian->fresh()->guardian_phone)->toBe('99345678');
});
