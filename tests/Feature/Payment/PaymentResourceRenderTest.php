<?php

declare(strict_types=1);

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Program;
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

it('renders cross-branch application context through projected scalar fields', function () {
    $program = Program::factory()->create(['name' => 'Projected Program']);
    $application = Application::factory()->create([
        'student_name' => 'Projected Student',
        'program_id' => $program->id,
    ]);
    $payment = Payment::factory()->forApplication($application)->create();

    expect($payment->fresh()->application)->toBeNull();

    Livewire::test(ListPayments::class)
        ->assertSuccessful()
        ->assertSee($application->ref_no)
        ->assertSee('Projected Student')
        ->assertSee('Projected Program')
        ->assertSee($application->branch->name);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSuccessful()
        ->assertSee($application->ref_no)
        ->assertSee('Projected Student')
        ->assertSee('Projected Program');
});

it('shows a safe Thawani checkout link on the payment view', function () {
    $payment = Payment::factory()->thawani()->create([
        'provider_checkout_url' => 'https://uatcheckout.thawani.om/pay/session?key=public',
    ]);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('https://uatcheckout.thawani.om/pay/session?key=public');
});

it('uses the projection for an ordinary branch user without widening the payment scope', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $ownApplication = Application::factory()->create([
        'branch_id' => $ownBranch->id,
        'student_name' => 'Own Branch Student',
    ]);
    $otherApplication = Application::factory()->create([
        'branch_id' => $otherBranch->id,
        'student_name' => 'Other Branch Student',
    ]);
    $ownPayment = Payment::factory()->forApplication($ownApplication)->create();
    $otherPayment = Payment::factory()->forApplication($otherApplication)->create();
    $branchUser = User::factory()->create(['branch_id' => $ownBranch->id]);
    $branchUser->givePermissionTo([
        Permission::firstOrCreate(['name' => 'ViewAny:Payment', 'guard_name' => 'web']),
        Permission::firstOrCreate(['name' => 'View:Payment', 'guard_name' => 'web']),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($branchUser);

    Livewire::test(ListPayments::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$ownPayment])
        ->assertCanNotSeeTableRecords([$otherPayment])
        ->assertSee('Own Branch Student')
        ->assertDontSee('Other Branch Student');
});
