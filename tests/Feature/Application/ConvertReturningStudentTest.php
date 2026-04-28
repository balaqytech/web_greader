<?php

use App\Actions\Applications\ConvertLeadToApplicationAction;
use App\Models\Guardian;
use App\Models\Lead;
use App\Models\Student;
use App\States\Applications\PendingRegistration;
use App\States\Applications\UnderReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fast-tracks and prefills application when guardian phone and student name match', function () {
    // 1. Create existing Guardian and Student
    $guardian = Guardian::factory()->create(['phone' => '+9681234567890']);
    $student = Student::factory()->create([
        'guardian_id' => $guardian->id,
        'name' => 'Ahmad',
        'gender' => 'male',
        'birth_date' => '2010-01-01',
        'civil_number' => '987654321',
        'state' => 'StateA',
    ]);

    // override the father_data phone to match guardian
    $fatherData = $student->father_data;
    $fatherData['phone'] = '+9681234567890';
    $student->update(['father_data' => $fatherData]);

    // 2. Create Lead with matching phone and student name
    $lead = Lead::factory()->contactedLead()->create([
        'whatsapp' => '1234567890',
        'student_name' => 'Ahmad',
    ]);

    // 3. Convert
    $action = app(ConvertLeadToApplicationAction::class);
    $application = $action->execute($lead);

    // 4. Assertions
    expect($application->status->getValue())->toBe(\App\States\Applications\DataComplete::$name)
        ->and($application->student_gender->value)->toBe('male')
        ->and($application->student_birth_date->format('Y-m-d'))->toBe('2010-01-01')
        ->and($application->father_name)->toBe($lead->guardian_name) // Priority from Lead
        ->and($application->father_phone)->toBe('+9681234567890')
        ->and($application->mother_name)->toBe($student->mother_data['name'])
        ->and($application->relative_name)->toBe($student->relative_data['name']);
});

it('does not fast-track if student name does not match', function () {
    // 1. Create existing Guardian and Student
    $guardian = Guardian::factory()->create(['phone' => '+9681234567890']);
    $student = Student::factory()->create([
        'guardian_id' => $guardian->id,
        'name' => 'Sara', // Different name
    ]);

    // 2. Create Lead with matching phone but DIFFERENT student name
    $lead = Lead::factory()->contactedLead()->create([
        'whatsapp' => '1234567890',
        'student_name' => 'Ahmad',
    ]);

    // 3. Convert
    $action = app(ConvertLeadToApplicationAction::class);
    $application = $action->execute($lead);

    // 4. Assertions - Should remain pending
    expect($application->status->getValue())->toBe(PendingRegistration::$name);
});

it('preserves lead-provided data during prefill', function () {
    // 1. Create existing Guardian and Student
    $guardian = Guardian::factory()->create(['phone' => '+9681234567890']);
    $student = Student::factory()->create([
        'guardian_id' => $guardian->id,
        'name' => 'Ahmad',
        'father_data' => [
            'name' => 'Old Father Name', // This should not overwrite lead data
            'phone' => '+9681234567890',
            'is_guardian' => true,
        ],
    ]);

    // 2. Create Lead with specific data
    $lead = Lead::factory()->contactedLead()->create([
        'whatsapp' => '1234567890',
        'guardian_name' => 'New Father Name', // Lead provided
        'student_name' => 'Ahmad',
    ]);

    // 3. Convert
    $action = app(ConvertLeadToApplicationAction::class);
    $application = $action->execute($lead);

    // 4. Assertions - Lead data takes precedence
    expect($application->father_name)->toBe('New Father Name') // Preserved
        ->and($application->father_phone)->toBe('+9681234567890') // Prefilled from lead
        ->and($application->status->getValue())->toBe(PendingRegistration::$name); // Pending because it doesn't have all the required fields for data completion
});
