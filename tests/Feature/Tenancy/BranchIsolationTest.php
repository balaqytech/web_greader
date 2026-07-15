<?php

use App\Models\Application;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Lead;
use App\Models\ReadingAssessmentFormSubmission;
use App\Models\Scopes\BranchScope;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ShieldPermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function branchUser(?int $branchId): User
{
    return User::factory()->create(['branch_id' => $branchId]);
}

function grantPermission(User $user, string $permissionName): void
{
    $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
    $user->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function superAdminUser(): User
{
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Built directly rather than via Student::factory()/GuardianFactory: those factories hit a
 * fake()->state() call with no ar_SA provider (this app's configured APP_FAKER_LOCALE) and a
 * `relationship` column GuardianFactory sets that the guardians table doesn't have —
 * pre-existing factory gaps unrelated to tenancy. Every field used here beyond the two
 * required ones (guardians.name/phone, students.guardian_id/branch_id/name) is nullable.
 */
function createStudentForBranch(int $branchId): Student
{
    $guardian = Guardian::create([
        'name' => 'Guardian '.fake()->unique()->numerify('####'),
        'phone' => fake()->unique()->numerify('0#########'),
    ]);

    return Student::create([
        'guardian_id' => $guardian->id,
        'branch_id' => $branchId,
        'name' => 'Student '.fake()->unique()->numerify('####'),
    ]);
}

function createReadingAssessmentFormSubmission(int $branchId, string $whatsapp): ReadingAssessmentFormSubmission
{
    return ReadingAssessmentFormSubmission::create([
        'student_name' => 'Student '.fake()->unique()->numerify('####'),
        'age' => 10,
        'grade_level' => '4',
        'guardian_name' => 'Guardian',
        'whatsapp' => $whatsapp,
        'branch_id' => $branchId,
    ]);
}

it('lets a branch user see only their own branch\'s records, for every currently scoped model', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $applicationA = Application::factory()->create(['branch_id' => $branchA->id]);
    Application::factory()->create(['branch_id' => $branchB->id]);

    $leadA = Lead::factory()->create(['branch_id' => $branchA->id]);
    Lead::factory()->create(['branch_id' => $branchB->id]);

    $studentA = createStudentForBranch($branchA->id);
    createStudentForBranch($branchB->id);

    $submissionA = createReadingAssessmentFormSubmission($branchA->id, '0501110001');
    createReadingAssessmentFormSubmission($branchB->id, '0501110002');

    $this->actingAs(branchUser($branchA->id));

    expect(Application::pluck('id')->all())->toBe([$applicationA->id])
        ->and(Lead::pluck('id')->all())->toBe([$leadA->id])
        ->and(Student::pluck('id')->all())->toBe([$studentA->id])
        ->and(ReadingAssessmentFormSubmission::pluck('id')->all())->toBe([$submissionA->id]);
});

it('lets a model-specific ViewAllBranches permission bypass only its matching model', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    Application::factory()->create(['branch_id' => $branchA->id]);
    Application::factory()->create(['branch_id' => $branchB->id]);

    Lead::factory()->create(['branch_id' => $branchA->id]);
    Lead::factory()->create(['branch_id' => $branchB->id]);

    $user = branchUser($branchA->id);
    grantPermission($user, 'ViewAllBranches:Application');

    $this->actingAs($user);

    expect(Application::count())->toBe(2)
        ->and(Lead::count())->toBe(1);
});

it('shows a null-branch authenticated user with no bypass permission no records at all', function () {
    Application::factory()->create();
    Lead::factory()->create();

    $this->actingAs(branchUser(null));

    expect(Application::count())->toBe(0)
        ->and(Lead::count())->toBe(0);
});

it('lets super_admin see every branch\'s records', function () {
    Application::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    Application::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs(superAdminUser());

    expect(Application::count())->toBe(2);
});

it('does not let central_finance see cross-branch applications or leads', function () {
    Application::factory()->create();
    Lead::factory()->create();

    $this->seed(ShieldPermissionSeeder::class);
    $financeRole = Role::where('name', 'central_finance')->where('guard_name', 'web')->firstOrFail();

    $user = branchUser(null);
    $user->assignRole($financeRole);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);

    expect(Application::count())->toBe(0)
        ->and(Lead::count())->toBe(0);
});

it('denies a policy update on a cross-branch application loaded via withoutGlobalScope, even with the permission', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $application = Application::factory()->awaitingRegistrationFee()->create(['branch_id' => $branchB->id]);

    $user = branchUser($branchA->id);
    grantPermission($user, 'Update:Application');

    $crossBranchApplication = Application::withoutGlobalScope(BranchScope::class)->find($application->id);

    expect(Gate::forUser($user)->denies('update', $crossBranchApplication))->toBeTrue();
});

it('allows a policy update on an own-branch application in an editable state with the permission', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingRegistrationFee()->create(['branch_id' => $branch->id]);

    $user = branchUser($branch->id);
    grantPermission($user, 'Update:Application');

    expect(Gate::forUser($user)->allows('update', $application))->toBeTrue();
});

it('lets ViewAllBranches:Application permit the same cross-branch update the branch check would otherwise deny', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $application = Application::factory()->awaitingRegistrationFee()->create(['branch_id' => $branchB->id]);

    $user = branchUser($branchA->id);
    grantPermission($user, 'Update:Application');
    grantPermission($user, 'ViewAllBranches:Application');

    $crossBranchApplication = Application::withoutGlobalScope(BranchScope::class)->find($application->id);

    expect(Gate::forUser($user)->allows('update', $crossBranchApplication))->toBeTrue();
});

it('requires ViewAllBranches:Application for a branchless user to create in any branch', function () {
    $branch = Branch::factory()->create();
    $user = branchUser(null);
    grantPermission($user, 'Create:Application');

    expect(Gate::forUser($user)->denies('create', [Application::class, $branch]))->toBeTrue();

    grantPermission($user, 'ViewAllBranches:Application');

    expect(Gate::forUser($user)->allows('create', [Application::class, $branch]))->toBeTrue();
});

it('seeds the Shield permission foundation idempotently, granting central_finance only its intended cross-branch permission', function () {
    $this->seed(ShieldPermissionSeeder::class);
    $this->seed(ShieldPermissionSeeder::class);

    $financeRole = Role::where('name', 'central_finance')->where('guard_name', 'web')->firstOrFail();

    expect($financeRole->permissions->pluck('name')->sort()->values()->all())->toBe([
        'Refund:Payment',
        'VerifyBankTransfer:Payment',
        'View:Payment',
        'ViewAllBranches:Payment',
        'ViewAny:Payment',
    ]);

    expect(Role::where('name', 'branch_staff')->where('guard_name', 'web')->count())->toBe(1)
        ->and(Role::where('name', 'branch_manager')->where('guard_name', 'web')->count())->toBe(1)
        ->and(Role::where('name', 'central_finance')->where('guard_name', 'web')->count())->toBe(1);

    foreach ([
        'ViewAllBranches:Application',
        'ViewAllBranches:Lead',
        'ViewAllBranches:Student',
        'ViewAllBranches:ReadingAssessmentFormSubmission',
        'ViewAllBranches:Payment',
        'ViewAllBranches:ApplicationDocument',
        'ViewAny:Payment',
        'View:Payment',
        'VerifyBankTransfer:Payment',
        'Refund:Payment',
    ] as $permissionName) {
        expect(Permission::where('name', $permissionName)->where('guard_name', 'web')->count())->toBe(1);
    }
});
