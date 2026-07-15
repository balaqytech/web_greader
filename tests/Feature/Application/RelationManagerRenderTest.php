<?php

use App\Filament\Resources\Guardians\Pages\ViewGuardian;
use App\Filament\Resources\Guardians\RelationManagers\ApplicationsRelationManager as GuardianApplicationsRelationManager;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ApplicationsRelationManager as StudentApplicationsRelationManager;
use App\Models\Application;
use App\Models\Guardian;
use App\Models\User;
use App\States\Applications\Accepted;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Branchless with cross-branch access to every application and student — these tests
 * exercise relation manager rendering, not branch-ownership, and the accepted application
 * below (plus its student) is created with its own random branch. Application is
 * loaded directly by the relation managers under test; Student is loaded via
 * `$application->student` to resolve the owner record for the Student relation manager test,
 * so both need the bypass.
 */
beforeEach(function () {
    $user = User::factory()->create(['branch_id' => null]);

    $user->givePermissionTo([
        Permission::firstOrCreate(['name' => 'ViewAllBranches:Application', 'guard_name' => 'web']),
        Permission::firstOrCreate(['name' => 'ViewAllBranches:Student', 'guard_name' => 'web']),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);
});

function acceptedApplication(): Application
{
    $application = Application::factory()->awaitingBranchReview()->create();
    $application->status->transitionTo(Accepted::class);

    return $application->fresh();
}

it('renders the Student applications relation manager with the linked application', function () {
    $application = acceptedApplication();
    $student = $application->student;

    Livewire::test(StudentApplicationsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$application]);
});

it('renders the Guardian applications relation manager via the flat query', function () {
    $application = acceptedApplication();
    $guardian = Guardian::firstWhere('id_number', $application->father_id_number);

    Livewire::test(GuardianApplicationsRelationManager::class, [
        'ownerRecord' => $guardian,
        'pageClass' => ViewGuardian::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$application]);
});
