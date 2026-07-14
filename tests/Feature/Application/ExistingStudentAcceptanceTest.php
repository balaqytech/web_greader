<?php

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Enums\GuardianRelationship;
use App\Exceptions\StudentBranchConflictException;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Scopes\BranchScope;
use App\Models\Student;
use App\Models\StudentContact;
use App\Models\User;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingBranchReview;

function makeStudent(Branch $branch, string $civilNumber, string $name = 'Original Name'): Student
{
    $guardian = Guardian::create([
        'name' => 'Existing Guardian',
        'id_number' => 'G'.fake()->unique()->numerify('######'),
        'phone' => fake()->unique()->numerify('9########'),
    ]);

    return Student::create([
        'guardian_id' => $guardian->id,
        'branch_id' => $branch->id,
        'name' => $name,
        'civil_number' => $civilNumber,
    ]);
}

it('updates and links an existing same-branch student on acceptance', function () {
    $branch = Branch::factory()->create();
    $student = makeStudent($branch, 'CIV-SAME', 'Old Name');

    $application = Application::factory()->awaitingBranchReview()->create([
        'branch_id' => $branch->id,
        'student_civil_number' => 'CIV-SAME',
        'student_name' => 'New Name',
    ]);

    $application->status->transitionTo(Accepted::class);
    $application->refresh();
    $student->refresh();

    expect(Student::withoutGlobalScope(BranchScope::class)->where('civil_number', 'CIV-SAME')->count())->toBe(1)
        ->and($student->name)->toBe('New Name')
        ->and($application->student_id)->toBe($student->id);
});

it('refuses to accept a student that belongs to another branch, with BranchScope active', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    // Authenticate a non-super-admin employee bound to branch B so BranchScope is genuinely
    // active: an ordinary read cannot see the branch-A student.
    $this->actingAs(User::factory()->create(['branch_id' => $branchB->id]));

    $student = makeStudent($branchA, 'CIV-CROSS');

    expect(Student::where('civil_number', 'CIV-CROSS')->exists())->toBeFalse()
        ->and(Student::withoutGlobalScope(BranchScope::class)->where('civil_number', 'CIV-CROSS')->exists())->toBeTrue();

    $application = Application::factory()->awaitingBranchReview()->create([
        'branch_id' => $branchB->id,
        'student_civil_number' => 'CIV-CROSS',
    ]);

    // Acceptance finds the cross-branch student only through the intentional scope bypass,
    // then refuses to mutate/transfer it.
    expect(fn () => $application->status->transitionTo(Accepted::class))
        ->toThrow(StudentBranchConflictException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->student_id)->toBeNull()
        ->and($student->fresh()->branch_id)->toBe($branchA->id);
});

it('fully synchronises contacts, removing stale ones on acceptance', function () {
    $branch = Branch::factory()->create();
    $student = makeStudent($branch, 'CIV-CONTACTS');

    StudentContact::create([
        'student_id' => $student->id,
        'relationship' => GuardianRelationship::Uncle,
        'name' => 'Stale Uncle',
        'is_guardian' => false,
    ]);

    $application = Application::factory()->awaitingBranchReview()->create([
        'branch_id' => $branch->id,
        'student_civil_number' => 'CIV-CONTACTS',
    ]);

    $application->status->transitionTo(Accepted::class);
    $student->refresh();

    // Factory populates father + mother only; the stale uncle must be gone.
    expect($student->contacts)->toHaveCount(2)
        ->and($student->contacts->pluck('name'))->not->toContain('Stale Uncle');
});

it('rolls back an existing-student update when acceptance fails', function () {
    app()->bind(RecordApplicationActivityAction::class, fn () => new class
    {
        public function handle($application, $fromState, $toState, $notes = null)
        {
            throw new RuntimeException('forced failure');
        }
    });

    $branch = Branch::factory()->create();
    $student = makeStudent($branch, 'CIV-ROLLBACK', 'Original Name');

    $application = Application::factory()->awaitingBranchReview()->create([
        'branch_id' => $branch->id,
        'student_civil_number' => 'CIV-ROLLBACK',
        'student_name' => 'Should Not Persist',
    ]);

    expect(fn () => $application->status->transitionTo(Accepted::class))
        ->toThrow(RuntimeException::class);

    $application->refresh();

    expect($student->fresh()->name)->toBe('Original Name')
        ->and($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->student_id)->toBeNull();
});
