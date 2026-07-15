<?php

use App\Actions\Leads\CreateLeadWithApplicationAction;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\Support\ManualEntryFixtures;

it('surfaces "program not available in branch" as a field-level validation error, not a raw exception', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    // Deliberately not attached to $branch.

    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    Auth::login(ManualEntryFixtures::authorizedManualEntryUser($branch->id));

    try {
        ManualEntryFixtures::manualEntryPage()->createRecordFromTrait($data);
        expect(false)->toBeTrue('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('program_id');
    }

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('surfaces an already-converted lead as a danger notification and halts without a raw exception reaching the caller', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

    $firstApplication = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
    $lead = Lead::find($firstApplication->lead_id);

    $secondData = ManualEntryFixtures::manualEntryData($branch, $program, [
        'student_name' => $data['student_name'],
        'father_phone' => $data['father_phone'],
    ]);
    Auth::login($user);

    // Halt is caught internally by Filament's CreateRecord::create() lifecycle; calling the
    // trait method directly here still lets Halt propagate (there is no page lifecycle to
    // swallow it), which is the correct, expected signal that record creation stopped
    // cleanly rather than crashing with the raw domain exception.
    expect(fn () => ManualEntryFixtures::manualEntryPage()->createRecordFromTrait($secondData))
        ->toThrow(Halt::class);

    Notification::assertNotified();

    expect(Application::count())->toBe(1)
        ->and($lead->fresh()->guardian_name)->toBe($lead->guardian_name);
});
