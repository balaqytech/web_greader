<?php

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Models\Application;
use App\Models\Guardian;
use App\Models\Student;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingBranchReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('atomically creates guardian, student, contacts and back-links student_id on acceptance', function () {
    $application = Application::factory()->awaitingBranchReview()->create();

    $application->status->transitionTo(Accepted::class);
    $application->refresh();

    $student = Student::firstWhere('civil_number', $application->student_civil_number);
    $guardian = Guardian::firstWhere('id_number', $application->father_id_number);

    expect($application->status)->toBeInstanceOf(Accepted::class)
        ->and($guardian)->not->toBeNull()
        ->and($student)->not->toBeNull()
        ->and($student->guardian_id)->toBe($guardian->id)
        ->and($student->branch_id)->toBe($application->branch_id)
        ->and($application->student_id)->toBe($student->id)
        // Factory populates father + mother (relative left blank) → two synced contacts.
        ->and($student->contacts)->toHaveCount(2)
        ->and($student->contacts->firstWhere('is_guardian', true)->id_number)->toBe($application->father_id_number)
        ->and($application->activities()->where('to_state', Accepted::getMorphClass())->exists())->toBeTrue();
});

it('rolls back student, guardian, student_id and state together on mid-transaction failure', function () {
    // Force a failure at the last step of the acceptance transaction (the activity write).
    app()->bind(RecordApplicationActivityAction::class, fn () => new class
    {
        public function handle($application, $fromState, $toState, $notes = null)
        {
            throw new RuntimeException('forced mid-transaction failure');
        }
    });

    $application = Application::factory()->awaitingBranchReview()->create();

    expect(fn () => $application->status->transitionTo(Accepted::class))
        ->toThrow(RuntimeException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->student_id)->toBeNull()
        ->and(Student::count())->toBe(0)
        ->and(Guardian::count())->toBe(0);
});
