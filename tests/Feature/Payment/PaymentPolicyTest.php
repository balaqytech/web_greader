<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function paymentPolicyUser(array $permissions = [], ?Branch $branch = null): User
{
    $user = User::factory()->create(['branch_id' => $branch?->id]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function paymentInBranch(Branch $branch, string $factoryState = 'pending'): Payment
{
    $application = Application::factory()->create(['branch_id' => $branch->id]);

    return Payment::factory()->forApplication($application)->{$factoryState}()->create();
}

it('allows viewAny with the permission', function () {
    expect(Gate::forUser(paymentPolicyUser(['ViewAny:Payment']))->allows('viewAny', Payment::class))->toBeTrue()
        ->and(Gate::forUser(paymentPolicyUser())->allows('viewAny', Payment::class))->toBeFalse();
});

it('allows viewing a payment in the user\'s own branch', function () {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['View:Payment'], $branch);

    expect(Gate::forUser($user)->allows('view', paymentInBranch($branch)))->toBeTrue();
});

it('denies viewing a payment in another branch', function () {
    $user = paymentPolicyUser(['View:Payment'], Branch::factory()->create());

    expect(Gate::forUser($user)->allows('view', paymentInBranch(Branch::factory()->create())))->toBeFalse();
});

it('allows central finance to view every branch through ViewAllBranches:Payment', function () {
    $user = paymentPolicyUser(['View:Payment', 'ViewAllBranches:Payment'], Branch::factory()->create());

    expect(Gate::forUser($user)->allows('view', paymentInBranch(Branch::factory()->create())))->toBeTrue();
});

it('denies viewing without the permission even in the user\'s own branch', function () {
    $branch = Branch::factory()->create();

    expect(Gate::forUser(paymentPolicyUser([], $branch))->allows('view', paymentInBranch($branch)))->toBeFalse();
});

it('allows verifying a bank transfer awaiting verification', function () {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['VerifyBankTransfer:Payment'], $branch);

    expect(Gate::forUser($user)->allows('verifyBankTransfer', paymentInBranch($branch, 'awaitingVerification')))->toBeTrue();
});

it('allows central finance to verify across branches', function () {
    $user = paymentPolicyUser(['VerifyBankTransfer:Payment', 'ViewAllBranches:Payment'], Branch::factory()->create());
    $payment = paymentInBranch(Branch::factory()->create(), 'awaitingVerification');

    expect(Gate::forUser($user)->allows('verifyBankTransfer', $payment))->toBeTrue();
});

/**
 * Verification is only meaningful for a bank transfer that is actually awaiting review.
 * Permitting it on another method or a settled attempt would be a route to marking a fee
 * paid without a receipt ever being looked at.
 */
it('denies verifying anything that is not a bank transfer awaiting verification', function (string $factoryState) {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['VerifyBankTransfer:Payment'], $branch);

    expect(Gate::forUser($user)->allows('verifyBankTransfer', paymentInBranch($branch, $factoryState)))->toBeFalse();
})->with(['pending', 'paid', 'failed', 'rejected', 'expired', 'cash', 'thawani']);

it('denies verifying without the permission', function () {
    $branch = Branch::factory()->create();

    expect(Gate::forUser(paymentPolicyUser([], $branch))->allows('verifyBankTransfer', paymentInBranch($branch, 'awaitingVerification')))->toBeFalse();
});

it('allows confirming a pending cash attempt with ConfirmCash:Payment', function () {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['ConfirmCash:Payment'], $branch);

    expect(Gate::forUser($user)->allows('confirmCash', paymentInBranch($branch, 'cash')))->toBeTrue();
});

/**
 * Confirming cash marks a fee paid with no verifiable money movement, so the finance
 * permission set deliberately does not reach it.
 */
it('denies confirming cash to a user holding every finance permission but not ConfirmCash', function () {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser([
        'ViewAny:Payment',
        'View:Payment',
        'VerifyBankTransfer:Payment',
        'ViewAllBranches:Payment',
    ], $branch);

    expect(Gate::forUser($user)->allows('confirmCash', paymentInBranch($branch, 'cash')))->toBeFalse();
});

it('denies confirming cash in another branch', function () {
    $user = paymentPolicyUser(['ConfirmCash:Payment'], Branch::factory()->create());

    expect(Gate::forUser($user)->allows('confirmCash', paymentInBranch(Branch::factory()->create(), 'cash')))->toBeFalse();
});

it('denies confirming cash on a non-cash or already-settled attempt', function (string $factoryState) {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['ConfirmCash:Payment'], $branch);

    expect(Gate::forUser($user)->allows('confirmCash', paymentInBranch($branch, $factoryState)))->toBeFalse();
})->with(['bankTransfer', 'awaitingVerification', 'paid', 'failed', 'rejected', 'expired']);

/**
 * Initiation is checked against the application it would belong to — no Payment row exists
 * yet at this point.
 */
it('allows creating an attempt in the user\'s own branch with Create:Payment', function () {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['Create:Payment'], $branch);
    $application = Application::factory()->create(['branch_id' => $branch->id]);

    expect(Gate::forUser($user)->allows('create', [Payment::class, $application]))->toBeTrue();
});

it('denies creating an attempt in another branch', function () {
    $user = paymentPolicyUser(['Create:Payment'], Branch::factory()->create());
    $application = Application::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    expect(Gate::forUser($user)->allows('create', [Payment::class, $application]))->toBeFalse();
});

it('denies creating an attempt without Create:Payment even in the user\'s own branch', function () {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser([], $branch);
    $application = Application::factory()->create(['branch_id' => $branch->id]);

    expect(Gate::forUser($user)->allows('create', [Payment::class, $application]))->toBeFalse();
});

it('allows uploading a receipt for a pending bank transfer in the user\'s own branch', function () {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['Update:Payment'], $branch);

    expect(Gate::forUser($user)->allows('uploadReceipt', paymentInBranch($branch, 'bankTransfer')))->toBeTrue();
});

it('denies uploading a receipt for a non-bank-transfer or non-pending attempt', function (string $factoryState) {
    $branch = Branch::factory()->create();
    $user = paymentPolicyUser(['Update:Payment'], $branch);

    expect(Gate::forUser($user)->allows('uploadReceipt', paymentInBranch($branch, $factoryState)))->toBeFalse();
})->with(['thawani', 'cash', 'awaitingVerification', 'paid']);

it('denies uploading a receipt without Update:Payment or in another branch', function () {
    $branch = Branch::factory()->create();

    expect(Gate::forUser(paymentPolicyUser([], $branch))->allows('uploadReceipt', paymentInBranch($branch, 'bankTransfer')))->toBeFalse()
        ->and(Gate::forUser(paymentPolicyUser(['Update:Payment'], Branch::factory()->create()))
            ->allows('uploadReceipt', paymentInBranch($branch, 'bankTransfer')))->toBeFalse();
});
