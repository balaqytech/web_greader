<?php

use App\Models\Application;
use App\Models\Branch;
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

    $studentA = Student::factory()->create(['branch_id' => $branchA->id]);
    Student::factory()->create(['branch_id' => $branchB->id]);

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

/**
 * @return list<string>
 */
function shieldPermissionFoundationPermissionNames(): array
{
    return [
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
    ];
}

it('seeds the Shield permission foundation idempotently, resetting central_finance to only its intended cross-branch permission on reseed', function () {
    $this->seed(ShieldPermissionSeeder::class);

    $financeRole = Role::where('name', 'central_finance')->where('guard_name', 'web')->firstOrFail();

    // Simulates drift between seeder runs (e.g. a manual admin grant): an unrelated
    // permission attached directly to the role must not survive a reseed — central_finance's
    // permission set is fully owned by this seeder, not additive.
    $unrelatedPermission = Permission::firstOrCreate(['name' => 'ViewAllBranches:Lead', 'guard_name' => 'web']);
    $financeRole->givePermissionTo($unrelatedPermission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // A real user, not just the role's own permissions() relation, so the check below goes
    // through Spatie's actual permission-resolution API.
    $financeUser = User::factory()->create();
    $financeUser->assignRole($financeRole);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Primes Spatie's permission cache with the pre-reseed state before reseeding below.
    expect($financeUser->can('ViewAllBranches:Lead'))->toBeTrue();

    $this->seed(ShieldPermissionSeeder::class);
    $this->seed(ShieldPermissionSeeder::class);

    // Deliberately no forgetCachedPermissions() call here: ShieldPermissionSeeder must clear
    // Spatie's permission cache itself (before and after syncing), so the primed cache above
    // never leaks a stale result into the assertions below. If either of the seeder's own
    // cache-clearing calls were removed, this test must fail rather than pass because this
    // test happened to clear the cache for it.
    $financeUser = $financeUser->fresh();

    $intendedFinancePermissions = [
        'Refund:Payment',
        'VerifyBankTransfer:Payment',
        'View:Payment',
        'ViewAllBranches:Payment',
        'ViewAny:Payment',
    ];

    expect($financeUser->can('ViewAllBranches:Lead'))->toBeFalse();

    foreach ($intendedFinancePermissions as $permissionName) {
        expect($financeUser->can($permissionName))->toBeTrue();
    }

    expect($financeUser->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect($intendedFinancePermissions)->sort()->values()->all());

    expect(Role::where('name', 'branch_staff')->where('guard_name', 'web')->count())->toBe(1)
        ->and(Role::where('name', 'branch_manager')->where('guard_name', 'web')->count())->toBe(1)
        ->and(Role::where('name', 'central_finance')->where('guard_name', 'web')->count())->toBe(1);

    foreach (shieldPermissionFoundationPermissionNames() as $permissionName) {
        expect(Permission::where('name', $permissionName)->where('guard_name', 'web')->count())->toBe(1);
    }
});

/**
 * config/filament-shield.php has super_admin.define_via_gate = false, so Shield does not
 * register a Gate::before/after interception for it — super_admin must genuinely hold every
 * permission to be unrestricted. Checked through a real user via Spatie's own can()/
 * getAllPermissions() API, not by asserting the role's raw permissions() relationship.
 */
it('grants super_admin every permission the foundation seeder creates, including the finance permissions', function () {
    $this->seed(ShieldPermissionSeeder::class);

    $user = superAdminUser();

    foreach (shieldPermissionFoundationPermissionNames() as $permissionName) {
        expect($user->can($permissionName))->toBeTrue();
    }

    expect($user->can('VerifyBankTransfer:Payment'))->toBeTrue()
        ->and($user->can('Refund:Payment'))->toBeTrue();
});

it('grants super_admin every pre-existing web permission too, but never a same-named permission on another guard', function () {
    // Simulates a permission ShieldSeeder already generated for an existing resource before
    // this seeder ever runs — super_admin's sync must cover it, not only the permissions
    // this seeder itself creates.
    $preExistingPermission = Permission::firstOrCreate(['name' => 'ViewAny:Lead', 'guard_name' => 'web']);

    // Same name, different guard — must never leak into a web-guard role's permission set.
    $affiliateGuardPermission = Permission::firstOrCreate(['name' => 'ViewAny:Lead', 'guard_name' => 'affiliate']);

    $this->seed(ShieldPermissionSeeder::class);

    $user = superAdminUser();

    expect($user->can($preExistingPermission->name))->toBeTrue();

    $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->firstOrFail();

    expect($superAdminRole->permissions->pluck('id'))->not->toContain($affiliateGuardPermission->id)
        ->and($superAdminRole->permissions->pluck('guard_name')->unique()->all())->toBe(['web']);
});
