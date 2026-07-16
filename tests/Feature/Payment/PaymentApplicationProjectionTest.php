<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use App\Support\Payments\PaymentApplicationProjection;
use App\Support\Payments\PaymentApplicationProjectionRow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function projectionUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('refuses an unauthenticated caller', function () {
    expect(fn () => app(PaymentApplicationProjection::class)->current())
        ->toThrow(AuthorizationException::class);
});

it('refuses a caller without ViewAllBranches:Payment', function () {
    test()->actingAs(projectionUser(['View:Payment']));

    expect(fn () => app(PaymentApplicationProjection::class)->current())
        ->toThrow(AuthorizationException::class);
});

it('refuses a caller with ViewAllBranches:Payment but neither View:Payment nor VerifyBankTransfer:Payment', function () {
    test()->actingAs(projectionUser(['ViewAllBranches:Payment']));

    expect(fn () => app(PaymentApplicationProjection::class)->current())
        ->toThrow(AuthorizationException::class);
});

it('allows a caller with ViewAllBranches:Payment and View:Payment', function () {
    test()->actingAs(projectionUser(['ViewAllBranches:Payment', 'View:Payment']));

    expect(app(PaymentApplicationProjection::class)->current())->toBeInstanceOf(Collection::class);
});

it('projects application reference, student name, program, branch, and fee amount across branches', function () {
    $user = projectionUser(['ViewAllBranches:Payment', 'View:Payment']);
    test()->actingAs($user);

    $branchA = Branch::factory()->create(['name' => 'Branch A']);
    $applicationA = Application::factory()->create(['branch_id' => $branchA->id, 'student_name' => 'Student A']);
    Payment::factory()->forApplication($applicationA)->amount('25.000')->pending()->create();

    $branchB = Branch::factory()->create(['name' => 'Branch B']);
    $applicationB = Application::factory()->create(['branch_id' => $branchB->id, 'student_name' => 'Student B']);
    Payment::factory()->forApplication($applicationB)->amount('30.000')->paid()->create();

    $rows = app(PaymentApplicationProjection::class)->current();

    expect($rows)->toHaveCount(2);

    $byReference = $rows->keyBy('applicationReference');
    expect($byReference->get($applicationA->ref_no)->studentName)->toBe('Student A')
        ->and($byReference->get($applicationA->ref_no)->branchName)->toBe('Branch A')
        ->and($byReference->get($applicationA->ref_no)->feeAmount)->toBe('25.000')
        ->and($byReference->get($applicationB->ref_no)->studentName)->toBe('Student B')
        ->and($byReference->get($applicationB->ref_no)->feeAmount)->toBe('30.000');
});

it('never exposes a raw Application model, only the projected row', function () {
    $user = projectionUser(['ViewAllBranches:Payment', 'View:Payment']);
    test()->actingAs($user);

    $application = Application::factory()->create();
    Payment::factory()->forApplication($application)->pending()->create();

    $rows = app(PaymentApplicationProjection::class)->current();

    expect($rows->first())->not->toBeInstanceOf(Application::class)
        ->and($rows->first())->toBeInstanceOf(PaymentApplicationProjectionRow::class);
});

it('shows only one row per application even with multiple payment attempts', function () {
    $user = projectionUser(['ViewAllBranches:Payment', 'View:Payment']);
    test()->actingAs($user);

    $application = Application::factory()->create();
    Payment::factory()->forApplication($application)->failed()->create();
    Payment::factory()->forApplication($application)->amount('25.000')->pending()->create();

    $rows = app(PaymentApplicationProjection::class)->current();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->feeAmount)->toBe('25.000');
});
