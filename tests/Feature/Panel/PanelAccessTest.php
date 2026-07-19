<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantRole(User $user, string $roleName): void
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function grantAccessPanelPermission(User $user): void
{
    $permission = Permission::firstOrCreate(['name' => 'Access:Panel', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('allows every role Shield names as an admin-panel role', function (string $role) {
    $user = User::factory()->create();
    grantRole($user, $role);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
})->with([
    'super_admin',
    'branch_staff',
    'branch_manager',
    'central_finance',
    'panel_user',
]);

it('allows a roleless user holding only the explicit Access:Panel permission', function () {
    $user = User::factory()->create();
    grantAccessPanelPermission($user);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});

it('denies a roleless, permissionless user', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('denies a user holding an unrelated role', function () {
    $user = User::factory()->create();
    grantRole($user, 'test');

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('denies service_fasih without the explicit Access:Panel permission', function () {
    $user = User::factory()->create();
    grantRole($user, 'service_fasih');

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('still denies service_fasih even when explicitly granted Access:Panel', function () {
    // Phase 5: the headless service principal is barred from the panel unconditionally, so a
    // leaked API credential can never become a panel session — even if Access:Panel is
    // accidentally granted.
    $user = User::factory()->create();
    grantRole($user, 'service_fasih');
    grantAccessPanelPermission($user);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('forbids actual HTTP access to /admin for a denied authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('allows actual HTTP access to /admin for an authenticated super_admin', function () {
    $user = User::factory()->create();
    grantRole($user, 'super_admin');

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});
