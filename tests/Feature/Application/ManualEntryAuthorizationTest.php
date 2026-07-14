<?php

use App\Actions\Leads\CreateLeadWithApplicationAction;
use App\Exceptions\LeadAlreadyConvertedException;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Season;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function superAdminUser(?int $branchId = null): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);

    $permission = Permission::firstOrCreate(['name' => 'Create:Application', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('allows a branch employee to create an application in their own branch', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    $user = authorizedManualEntryUser($branch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);

    expect($application)->toBeInstanceOf(Application::class)
        ->and(Application::count())->toBe(1);
});

it('rejects a tampered cross-branch request from a branch employee with zero writes', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $otherBranch = Branch::factory()->create();

    // The acting user belongs to $otherBranch, but the submitted branch_id targets $branch —
    // a tampered request (client-side branch select was disabled, but the request payload
    // was edited directly).
    $data = manualEntryData($branch, $program);
    $user = authorizedManualEntryUser($otherBranch->id);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(AuthorizationException::class);

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('allows a branchless central user to create in any branch', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    $user = authorizedManualEntryUser(null);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);

    expect(Application::count())->toBe(1)
        ->and($application->branch_id)->toBe($branch->id);
});

it('allows super_admin to create in a branch other than their own', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $otherBranch = Branch::factory()->create();
    $data = manualEntryData($branch, $program);
    $user = superAdminUser($otherBranch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);

    expect(Application::count())->toBe(1)
        ->and($application->branch_id)->toBe($branch->id);
});

it('rejects a user missing the Create:Application permission entirely, with zero writes', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    $user = User::factory()->create(['branch_id' => $branch->id]);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(AuthorizationException::class);

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('authorizes before any lead lookup, merge, or write', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $otherBranch = Branch::factory()->create();

    // A pre-existing lead that would otherwise dedup-match the tampered request, so if
    // authorization ran *after* the duplicate lookup this would mutate it before failing.
    $existingLead = Lead::factory()->create([
        'whatsapp' => '+96891234567',
        'branch_id' => $branch->id,
        'program_id' => $program->id,
        'guardian_name' => 'Original Name',
    ]);
    $originalGuardianName = $existingLead->guardian_name;

    $data = manualEntryData($branch, $program, [
        'father_phone' => '091234567',
        'father_is_guardian' => true,
        'mother_is_guardian' => false,
    ]);
    $user = authorizedManualEntryUser($otherBranch->id);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(AuthorizationException::class);

    expect($existingLead->fresh()->guardian_name)->toBe($originalGuardianName)
        ->and(Application::count())->toBe(0);
});

it('detects an already-converted lead even when its application predates a branch reassignment', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $reassignedBranch = Branch::factory()->create();
    $program->branches()->attach($reassignedBranch, ['price' => 100]);

    $season = Season::current($program->type);

    // Simulates a lead that was converted while it belonged to $branch, then later
    // reassigned to $reassignedBranch elsewhere in the app — its existing application
    // retains the *original* branch_id, so the two now disagree.
    $lead = Lead::factory()->create([
        'whatsapp' => '+96891234567',
        'student_name' => 'Reassigned Student',
        'program_id' => $program->id,
        'branch_id' => $reassignedBranch->id,
        'season_id' => $season->id,
    ]);
    Application::factory()->create([
        'lead_id' => $lead->id,
        'branch_id' => $branch->id,
        'program_id' => $program->id,
        'season_id' => $season->id,
    ]);

    // Acting as an employee of $reassignedBranch (the lead's *current* branch — the only
    // branch they're authorized to act in): if the conversion check were scoped by
    // BranchScope to the acting user's own branch_id, it would miss the application (whose
    // branch_id is $branch's, not $reassignedBranch's) and let a second, illegal application
    // through for the same lead.
    $data = manualEntryData($reassignedBranch, $program, [
        'student_name' => $lead->student_name,
        'father_phone' => '091234567',
        'father_is_guardian' => true,
        'mother_is_guardian' => false,
    ]);
    $user = authorizedManualEntryUser($reassignedBranch->id);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(LeadAlreadyConvertedException::class);

    expect(Application::count())->toBe(1);
});

it('passes the authenticated Filament user through to the composite action', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    Auth::login(authorizedManualEntryUser($branch->id));

    $page = new class extends CreateApplication
    {
        public function createRecordFromTrait(array $data): Application
        {
            return $this->handleRecordCreation($data);
        }
    };

    $application = $page->createRecordFromTrait($data);

    expect($application)->toBeInstanceOf(Application::class);
});

it('surfaces a branch-authorization failure as a field-level validation error on the Filament page', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $otherBranch = Branch::factory()->create();
    $data = manualEntryData($branch, $program);
    Auth::login(authorizedManualEntryUser($otherBranch->id));

    $page = new class extends CreateApplication
    {
        public function createRecordFromTrait(array $data): Application
        {
            return $this->handleRecordCreation($data);
        }
    };

    try {
        $page->createRecordFromTrait($data);
        expect(false)->toBeTrue('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('branch_id');
    }

    expect(Application::count())->toBe(0)
        ->and(Lead::count())->toBe(0);
});
