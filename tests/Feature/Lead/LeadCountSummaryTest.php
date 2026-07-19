<?php

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;

/**
 * These tests exercise cross-branch count summaries via the Fasih service account. The counts
 * endpoint bypasses BranchScope inside its authorized query (the branchless service principal
 * has no branch to scope to), so no Shield cross-branch grant is involved.
 */
beforeEach(function () {
    [, $token] = fasihServiceToken();
    $this->withHeader('Authorization', "Bearer {$token}");
});

it('returns lead counts per branch and per program within each branch', function () {
    $branchOne = Branch::factory()->create(['name' => 'Branch One']);
    $branchTwo = Branch::factory()->create(['name' => 'Branch Two']);
    $programOne = Program::factory()->create(['name' => 'Program One']);
    $programTwo = Program::factory()->create(['name' => 'Program Two']);

    Lead::factory()->count(2)->create([
        'branch_id' => $branchOne->id,
        'program_id' => $programOne->id,
    ]);
    Lead::factory()->create([
        'branch_id' => $branchOne->id,
        'program_id' => $programTwo->id,
    ]);
    Lead::factory()->count(3)->create([
        'branch_id' => $branchTwo->id,
        'program_id' => $programOne->id,
    ]);

    $response = $this->getJson('/api/v1/leads/counts');

    $response->assertOk()
        ->assertJsonPath('data.total_leads', 6)
        ->assertJsonPath('data.branches.0.branch_id', $branchOne->id)
        ->assertJsonPath('data.branches.0.leads_count', 3)
        ->assertJsonPath('data.branches.1.branch_id', $branchTwo->id)
        ->assertJsonPath('data.branches.1.leads_count', 3)
        ->assertJsonPath('data.programs_by_branch.0.branch_id', $branchOne->id)
        ->assertJsonPath('data.programs_by_branch.0.program_id', $programOne->id)
        ->assertJsonPath('data.programs_by_branch.0.leads_count', 2)
        ->assertJsonPath('data.programs_by_branch.1.branch_id', $branchOne->id)
        ->assertJsonPath('data.programs_by_branch.1.program_id', $programTwo->id)
        ->assertJsonPath('data.programs_by_branch.1.leads_count', 1)
        ->assertJsonPath('data.programs_by_branch.2.branch_id', $branchTwo->id)
        ->assertJsonPath('data.programs_by_branch.2.program_id', $programOne->id)
        ->assertJsonPath('data.programs_by_branch.2.leads_count', 3);
});

it('filters lead counts to a specific branch and program', function () {
    $branchOne = Branch::factory()->create();
    $branchTwo = Branch::factory()->create();
    $programOne = Program::factory()->create();
    $programTwo = Program::factory()->create();

    Lead::factory()->count(2)->create([
        'branch_id' => $branchOne->id,
        'program_id' => $programOne->id,
    ]);
    Lead::factory()->create([
        'branch_id' => $branchOne->id,
        'program_id' => $programTwo->id,
    ]);
    Lead::factory()->create([
        'branch_id' => $branchTwo->id,
        'program_id' => $programOne->id,
    ]);

    $response = $this->getJson("/api/v1/leads/counts?branch_id={$branchOne->id}&program_id={$programOne->id}");

    $response->assertOk()
        ->assertJsonPath('data.total_leads', 2)
        ->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.branches.0.branch_id', $branchOne->id)
        ->assertJsonPath('data.branches.0.leads_count', 2)
        ->assertJsonCount(1, 'data.programs_by_branch')
        ->assertJsonPath('data.programs_by_branch.0.branch_id', $branchOne->id)
        ->assertJsonPath('data.programs_by_branch.0.program_id', $programOne->id)
        ->assertJsonPath('data.programs_by_branch.0.leads_count', 2);
});
