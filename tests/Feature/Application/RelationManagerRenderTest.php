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

beforeEach(function () {
    $this->actingAs(User::factory()->create(['branch_id' => null]));
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
