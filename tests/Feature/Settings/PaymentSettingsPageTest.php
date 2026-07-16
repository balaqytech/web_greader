<?php

declare(strict_types=1);

use App\Filament\Pages\PaymentSettingsPage;
use App\Models\User;
use Database\Seeders\ShieldPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Authorization for the payment-settings page.
 *
 * Access is asserted through `canAccess()` and through a real HTTP request. Filament form
 * *rendering* cannot be exercised in this environment (a known infrastructure limitation —
 * see ProgramResourceTest), so a 200-with-rendered-form assertion is not attempted here; the
 * denial path aborts before any rendering and is the security-relevant one. Field validation
 * is covered where the rule actually lives, in OmrAmountTest and PositiveOmrAmountTest.
 */
function paymentSettingsUser(array $permissions = [], ?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
    }

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('denies access without the Manage:PaymentSettings permission', function () {
    $this->actingAs(paymentSettingsUser(role: 'branch_staff'));

    expect(PaymentSettingsPage::canAccess())->toBeFalse();
});

it('allows access with the Manage:PaymentSettings permission', function () {
    $this->actingAs(paymentSettingsUser(['Manage:PaymentSettings']));

    expect(PaymentSettingsPage::canAccess())->toBeTrue();
});

it('denies a guest', function () {
    expect(PaymentSettingsPage::canAccess())->toBeFalse();
});

/**
 * The permission decides what every future applicant is charged, so no ordinary operational
 * role carries it — including central finance, which handles payments but does not price them.
 */
it('denies every role seeded by the permission foundation', function (string $role) {
    $this->seed(ShieldPermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(Role::where('name', $role)->where('guard_name', 'web')->firstOrFail());
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);

    expect(PaymentSettingsPage::canAccess())->toBeFalse();
})->with(['branch_staff', 'branch_manager', 'central_finance']);

it('allows super_admin, which holds the permission through the blanket sync', function () {
    $this->seed(ShieldPermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(Role::where('name', 'super_admin')->where('guard_name', 'web')->firstOrFail());
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);

    expect(PaymentSettingsPage::canAccess())->toBeTrue();
});

it('rejects an unauthorized HTTP request to the page', function () {
    $this->seed(ShieldPermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(Role::where('name', 'central_finance')->where('guard_name', 'web')->firstOrFail());
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user)
        ->get(PaymentSettingsPage::getUrl(panel: 'admin'))
        ->assertForbidden();
});

it('rejects an HTTP request from a user with panel access but no payment-settings permission', function () {
    $user = paymentSettingsUser(['Access:Panel']);

    $this->actingAs($user)
        ->get(PaymentSettingsPage::getUrl(panel: 'admin'))
        ->assertForbidden();
});
