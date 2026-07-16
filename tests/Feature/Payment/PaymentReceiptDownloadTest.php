<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use App\Support\Payments\PaymentReceiptStorage;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
});

function receiptViewer(Branch $branch, array $permissions): User
{
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function paymentWithReceipt(Branch $branch): Payment
{
    $application = Application::factory()->create(['branch_id' => $branch->id]);
    $path = 'receipts/private-receipt.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 private receipt');

    return Payment::factory()->forApplication($application)->bankTransfer()->create([
        'receipt_path' => $path,
    ]);
}

it('allows an authorized branch user to download an own-branch private receipt', function () {
    $branch = Branch::factory()->create();
    $payment = paymentWithReceipt($branch);

    $response = $this->actingAs(receiptViewer($branch, ['View:Payment']))
        ->get(route('payments.receipt.download', $payment));

    $response->assertOk()
        ->assertHeader('content-disposition');
    expect($response->streamedContent())->toBe('%PDF-1.4 private receipt');
});

it('allows central finance to download a cross-branch receipt without application access', function () {
    $paymentBranch = Branch::factory()->create();
    $financeBranch = Branch::factory()->create();
    $payment = paymentWithReceipt($paymentBranch);
    $finance = receiptViewer($financeBranch, ['ViewAllBranches:Payment', 'View:Payment']);

    $this->actingAs($finance)
        ->get(route('payments.receipt.download', $payment))
        ->assertOk();

    expect($finance->can('ViewAllBranches:Application'))->toBeFalse();
});

it('does not expose another branch receipt to an ordinary branch user', function () {
    $paymentBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $payment = paymentWithReceipt($paymentBranch);

    $this->actingAs(receiptViewer($otherBranch, ['View:Payment']))
        ->get(route('payments.receipt.download', $payment))
        ->assertNotFound();
});

it('returns not found when the referenced private receipt is missing', function () {
    $branch = Branch::factory()->create();
    $payment = paymentWithReceipt($branch);
    Storage::disk('local')->delete($payment->receipt_path);

    $this->actingAs(receiptViewer($branch, ['View:Payment']))
        ->get(route('payments.receipt.download', $payment))
        ->assertNotFound();
});

it('deletes only unreferenced receipt candidates during compensation', function () {
    $branch = Branch::factory()->create();
    $payment = paymentWithReceipt($branch);
    $unreferenced = 'receipts/unreferenced.pdf';
    Storage::disk('local')->put($unreferenced, 'temporary');
    $receipts = app(PaymentReceiptStorage::class);

    $receipts->deleteIfUnreferenced($payment->receipt_path);
    $receipts->deleteIfUnreferenced($unreferenced);

    Storage::disk('local')->assertExists($payment->receipt_path);
    Storage::disk('local')->assertMissing($unreferenced);
});
