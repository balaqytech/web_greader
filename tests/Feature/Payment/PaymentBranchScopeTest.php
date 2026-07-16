<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function paymentScopeUser(?Branch $branch, array $permissions = [], ?string $role = null): User
{
    $user = User::factory()->create(['branch_id' => $branch?->id]);

    if ($role !== null) {
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
    }

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function paymentForBranch(Branch $branch): Payment
{
    return Payment::factory()
        ->forApplication(Application::factory()->create(['branch_id' => $branch->id]))
        ->create();
}

it('restricts a branch user to their own branch payments', function () {
    $own = Branch::factory()->create();
    $other = Branch::factory()->create();

    $mine = paymentForBranch($own);
    paymentForBranch($other);

    $this->actingAs(paymentScopeUser($own));

    expect(Payment::query()->pluck('id')->all())->toBe([$mine->id]);
});

it('shows every branch to a user holding ViewAllBranches:Payment', function () {
    $one = paymentForBranch(Branch::factory()->create());
    $two = paymentForBranch(Branch::factory()->create());

    $this->actingAs(paymentScopeUser(Branch::factory()->create(), ['ViewAllBranches:Payment']));

    expect(Payment::query()->pluck('id')->all())->toEqualCanonicalizing([$one->id, $two->id]);
});

it('shows every branch to super_admin', function () {
    $one = paymentForBranch(Branch::factory()->create());
    $two = paymentForBranch(Branch::factory()->create());

    $this->actingAs(paymentScopeUser(null, role: 'super_admin'));

    expect(Payment::query()->pluck('id')->all())->toEqualCanonicalizing([$one->id, $two->id]);
});

/**
 * A branchless user with no cross-branch permission must not fall through to "no filter".
 */
it('shows nothing to a branchless user without cross-branch access', function () {
    paymentForBranch(Branch::factory()->create());

    $this->actingAs(paymentScopeUser(null));

    expect(Payment::query()->count())->toBe(0);
});

/**
 * Payments have no affiliate-ownership concept, so an authenticated affiliate must fail
 * closed here rather than see the whole table.
 */
it('shows nothing to an authenticated affiliate', function () {
    paymentForBranch(Branch::factory()->create());

    $this->actingAs(Affiliate::factory()->create(), 'affiliate');

    expect(Payment::query()->count())->toBe(0);
});

it('leaves unauthenticated system contexts unscoped', function () {
    $one = paymentForBranch(Branch::factory()->create());
    $two = paymentForBranch(Branch::factory()->create());

    expect(Payment::query()->pluck('id')->all())->toEqualCanonicalizing([$one->id, $two->id]);
});

/**
 * The scope is a presentation filter. It must never hide a row from the locking primitive
 * that enforces the one-active/one-paid invariants, or a second attempt could be created
 * simply because the first was invisible to the acting user.
 */
it('can be bypassed explicitly so invariant enforcement always sees every row', function () {
    $other = paymentForBranch(Branch::factory()->create());

    $this->actingAs(paymentScopeUser(Branch::factory()->create()));

    expect(Payment::query()->count())->toBe(0)
        ->and(Payment::withoutGlobalScopes()->whereKey($other->id)->exists())->toBeTrue();
});
