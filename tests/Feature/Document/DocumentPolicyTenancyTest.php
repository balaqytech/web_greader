<?php

declare(strict_types=1);

use App\Models\ApplicationDocument;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Database\Seeders\ShieldPermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function branchUserWithDocPermissions(?int $branchId, array $permissions): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('lets a branch user see only their own branch documents', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $documentA = ApplicationDocument::factory()->create(['branch_id' => $branchA->id]);
    ApplicationDocument::factory()->create(['branch_id' => $branchB->id]);

    $this->actingAs(branchUserWithDocPermissions($branchA->id, ['ViewAny:ApplicationDocument']));

    expect(ApplicationDocument::pluck('id')->all())->toBe([$documentA->id]);
});

it('allows own-branch view, upload, and review with the matching permissions', function () {
    $branch = Branch::factory()->create();
    $document = ApplicationDocument::factory()->create(['branch_id' => $branch->id]);
    $user = branchUserWithDocPermissions($branch->id, [
        'View:ApplicationDocument', 'Upload:ApplicationDocument', 'Review:ApplicationDocument',
    ]);

    expect(Gate::forUser($user)->allows('view', $document))->toBeTrue()
        ->and(Gate::forUser($user)->allows('upload', $document))->toBeTrue()
        ->and(Gate::forUser($user)->allows('review', $document))->toBeTrue();
});

it('denies review on a cross-branch document even when loaded via an unscoped record', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $document = ApplicationDocument::factory()->create(['branch_id' => $branchB->id]);

    $user = branchUserWithDocPermissions($branchA->id, ['Review:ApplicationDocument']);
    $crossBranch = ApplicationDocument::withoutGlobalScope(BranchScope::class)->find($document->id);

    expect(Gate::forUser($user)->denies('review', $crossBranch))->toBeTrue();
});

it('fails closed for a null-branch user with no cross-branch permission', function () {
    $document = ApplicationDocument::factory()->create();
    $user = branchUserWithDocPermissions(null, ['View:ApplicationDocument']);

    expect(Gate::forUser($user)->denies('view', $document))->toBeTrue();
});

it('lets the model-specific ViewAllBranches permission bypass the branch check', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $document = ApplicationDocument::factory()->create(['branch_id' => $branchB->id]);

    $user = branchUserWithDocPermissions($branchA->id, ['View:ApplicationDocument', 'ViewAllBranches:ApplicationDocument']);
    $crossBranch = ApplicationDocument::withoutGlobalScope(BranchScope::class)->find($document->id);

    expect(Gate::forUser($user)->allows('view', $crossBranch))->toBeTrue();
});

it('grants super_admin access to every document ability', function () {
    $this->seed(ShieldPermissionSeeder::class);
    $document = ApplicationDocument::factory()->create();

    $user = User::factory()->create();
    $user->assignRole(Role::where('name', 'super_admin')->where('guard_name', 'web')->firstOrFail());
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $crossBranch = ApplicationDocument::withoutGlobalScope(BranchScope::class)->find($document->id);

    expect(Gate::forUser($user)->allows('view', $crossBranch))->toBeTrue()
        ->and(Gate::forUser($user)->allows('upload', $crossBranch))->toBeTrue()
        ->and(Gate::forUser($user)->allows('review', $crossBranch))->toBeTrue();
});

it('denies central_finance any document access', function () {
    $this->seed(ShieldPermissionSeeder::class);
    $document = ApplicationDocument::factory()->create();

    $user = User::factory()->create(['branch_id' => null]);
    $user->assignRole(Role::where('name', 'central_finance')->where('guard_name', 'web')->firstOrFail());
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $crossBranch = ApplicationDocument::withoutGlobalScope(BranchScope::class)->find($document->id);

    expect($user->can('ViewAny:ApplicationDocument'))->toBeFalse()
        ->and(Gate::forUser($user)->denies('view', $crossBranch))->toBeTrue()
        ->and(Gate::forUser($user)->denies('upload', $crossBranch))->toBeTrue()
        ->and(Gate::forUser($user)->denies('review', $crossBranch))->toBeTrue();
});
