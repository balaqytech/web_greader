<?php

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * A user who genuinely holds the Update:Application permission — so the tests exercise the
 * state guard, not the ordinary permission check.
 */
function authorizedApplicationEditor(): User
{
    $user = User::factory()->create(['branch_id' => null]);

    $permission = Permission::firstOrCreate(['name' => 'Update:Application', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('authorizes an employee to update an application still in data entry', function (string $factoryState) {
    $user = authorizedApplicationEditor();
    $application = Application::factory()->{$factoryState}()->create();

    expect(Gate::forUser($user)->allows('update', $application))->toBeTrue();
})->with(['awaitingRegistrationFee', 'awaitingApplicationCompletion']);

it('denies updating an application once past data entry, even with the permission', function (string $factoryState) {
    $user = authorizedApplicationEditor();
    $application = Application::factory()->{$factoryState}()->create();

    expect(Gate::forUser($user)->denies('update', $application))->toBeTrue();
})->with(['awaitingContractSignature', 'awaitingBranchReview', 'accepted', 'rejected', 'cancelled']);

it('preserves the ordinary permission check for editable states', function () {
    // Editable state, but the user lacks Update:Application → still denied.
    $user = User::factory()->create(['branch_id' => null]);
    $application = Application::factory()->awaitingApplicationCompletion()->create();

    expect(Gate::forUser($user)->denies('update', $application))->toBeTrue();
});

it('forbids direct route access to the edit page past data entry', function (string $factoryState) {
    // The edit route mounts the Livewire EditApplication page through the real request
    // lifecycle; a denied update policy aborts the mount before any edit is possible.
    $this->actingAs(authorizedApplicationEditor());
    $application = Application::factory()->{$factoryState}()->create();

    $this->get(ApplicationResource::getUrl('edit', ['record' => $application]))
        ->assertForbidden();
})->with(['awaitingContractSignature', 'awaitingBranchReview', 'accepted', 'rejected', 'cancelled']);
