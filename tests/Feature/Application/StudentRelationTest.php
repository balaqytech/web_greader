<?php

use App\Models\Application;
use App\Models\Guardian;
use App\Models\Student;
use App\States\Applications\Accepted;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links a student to its applications via student_id after acceptance', function () {
    $application = Application::factory()->awaitingBranchReview()->create();
    $application->status->transitionTo(Accepted::class);
    $application->refresh();

    $student = $application->student;

    expect($student)->toBeInstanceOf(Student::class)
        ->and($student->applications->pluck('id'))->toContain($application->id)
        ->and($application->student->id)->toBe($student->id);
});

it('resolves guardian applications from the flat schema without the removed relations', function () {
    // Acceptance creates the guardian from the flat father_* columns (father is the
    // acting guardian in the factory), so we exercise the flat getApplicationsQuery
    // against a real persisted guardian without relying on the standalone factory.
    $application = Application::factory()->awaitingBranchReview()->create();
    $application->status->transitionTo(Accepted::class);
    $application->refresh();

    $guardian = Guardian::firstWhere('id_number', $application->father_id_number);

    expect($guardian)->not->toBeNull()
        ->and($guardian->getApplicationsQuery()->pluck('id'))->toContain($application->id);
});

it('renders the student applications relation manager query without fatal', function () {
    $application = Application::factory()->awaitingBranchReview()->create();
    $application->status->transitionTo(Accepted::class);
    $student = $application->fresh()->student;

    // The Filament StudentResource ApplicationsRelationManager is backed by this relation;
    // exercising it proves the repointed hasMany replaces the removed HasManyThrough (C10).
    expect($student->applications()->get())->toHaveCount(1);
});
