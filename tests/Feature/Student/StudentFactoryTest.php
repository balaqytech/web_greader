<?php

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use App\Models\Student;

it('persists a guardian created by the factory', function () {
    $guardian = Guardian::factory()->create();

    expect($guardian)->toBeInstanceOf(Guardian::class)
        ->and($guardian->exists)->toBeTrue()
        ->and(Guardian::find($guardian->id))->not->toBeNull();
});

it('persists a student created by the factory with its generated guardian and branch', function () {
    $student = Student::factory()->create();

    expect($student)->toBeInstanceOf(Student::class)
        ->and($student->exists)->toBeTrue()
        ->and($student->guardian)->toBeInstanceOf(Guardian::class)
        ->and($student->branch)->not->toBeNull();
});

it('casts the persisted student\'s gender and guardian relationship to valid enum values', function () {
    $student = Student::factory()->create();

    expect($student->gender)->toBeInstanceOf(Gender::class)
        ->and(Gender::cases())->toContain($student->gender)
        ->and($student->relationship_with_guardian)->toBeInstanceOf(GuardianRelationship::class)
        ->and(GuardianRelationship::cases())->toContain($student->relationship_with_guardian);
});

it('creates multiple guardian and student factory records without collisions', function () {
    $guardians = Guardian::factory()->count(5)->create();
    $students = Student::factory()->count(5)->create();

    expect($guardians)->toHaveCount(5)
        ->and($guardians->pluck('id')->unique())->toHaveCount(5)
        ->and($guardians->pluck('phone')->unique())->toHaveCount(5)
        ->and($guardians->pluck('id_number')->unique())->toHaveCount(5)
        ->and($students)->toHaveCount(5)
        ->and($students->pluck('id')->unique())->toHaveCount(5)
        ->and($students->pluck('civil_number')->unique())->toHaveCount(5)
        ->and($students->pluck('branch.name')->unique())->toHaveCount(5)
        ->and($students->pluck('guardian.phone')->unique())->toHaveCount(5);
});
