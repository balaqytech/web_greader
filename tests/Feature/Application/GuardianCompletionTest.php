<?php

use App\Exceptions\ApplicationIncompleteException;
use App\Exceptions\GuardianConflictException;
use App\Models\Application;
use App\Models\Guardian;
use App\Models\Student;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;

it('requires the acting guardian name, id number, and phone to complete', function (array $override) {
    $application = Application::factory()->awaitingApplicationCompletion()->create($override + [
        'father_is_guardian' => true,
    ]);

    expect(fn () => $application->status->transitionTo(AwaitingContractSignature::class))
        ->toThrow(ApplicationIncompleteException::class);

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
})->with([
    'missing name' => [['father_name' => null]],
    'missing id number' => [['father_id_number' => null]],
    'missing phone' => [['father_phone' => null]],
]);

it('rejects acceptance when the guardian phone belongs to another guardian, with no partial writes', function () {
    Guardian::create([
        'name' => 'Other Guardian',
        'id_number' => 'OTHER-ID',
        'phone' => '99887766',
    ]);

    $application = Application::factory()->awaitingBranchReview()->create([
        'father_is_guardian' => true,
        'father_phone' => '99887766',
        'father_id_number' => '11112222',
    ]);

    expect(fn () => $application->status->transitionTo(Accepted::class))
        ->toThrow(GuardianConflictException::class);

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->student_id)->toBeNull()
        ->and(Student::count())->toBe(0)
        ->and(Guardian::where('id_number', '11112222')->exists())->toBeFalse();
});

it('updates a returning guardian by id number without a false phone conflict', function () {
    $first = Application::factory()->awaitingBranchReview()->create();
    $first->status->transitionTo(Accepted::class);

    // A second application for the same guardian (same id + phone) must update, not conflict.
    $second = Application::factory()->awaitingBranchReview()->create([
        'father_is_guardian' => true,
        'father_id_number' => $first->father_id_number,
        'father_phone' => $first->father_phone,
        'student_civil_number' => 'SECOND-STUDENT-CIVIL',
    ]);

    $second->status->transitionTo(Accepted::class);

    expect(Guardian::where('id_number', $first->father_id_number)->count())->toBe(1)
        ->and($second->fresh()->status)->toBeInstanceOf(Accepted::class);
});
