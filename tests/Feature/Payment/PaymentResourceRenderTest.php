<?php

declare(strict_types=1);

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Filament form rendering (fillForm) is broken in this environment (see project memory); this
 * sticks to table/infolist rendering, which does work under Livewire::test, and exists mainly
 * to catch class-wiring mistakes across PaymentResource, its table, infolist, and the four
 * mutation actions — a typo or bad import here fails loudly instead of only at click-time.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::firstOrCreate(['name' => 'ViewAny:Payment', 'guard_name' => 'web']),
        Permission::firstOrCreate(['name' => 'View:Payment', 'guard_name' => 'web']),
        Permission::firstOrCreate(['name' => 'ViewAllBranches:Payment', 'guard_name' => 'web']),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);
});

it('renders the payments list table', function () {
    $payment = Payment::factory()->create();

    Livewire::test(ListPayments::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$payment]);
});

it('renders a payment\'s view page', function () {
    $payment = Payment::factory()->create();

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSuccessful();
});
