<?php

use App\Actions\Applications\CreateApplicationAction;
use App\Actions\Leads\CreateLeadWithApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Source;
use App\Exceptions\LeadAlreadyConvertedException;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\States\Applications\AwaitingRegistrationFee;

function createAvailableBranchAndProgram(): array
{
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    $program->branches()->attach($branch, ['price' => 100]);

    return [$branch, $program];
}

/**
 * @return array<string, mixed>
 */
function manualEntryData(Branch $branch, Program $program, array $overrides = []): array
{
    return array_merge([
        'branch_id' => $branch->id,
        'program_id' => $program->id,

        'student_name' => 'Student '.fake()->unique()->numerify('####'),
        'student_gender' => Gender::Male,
        'student_birth_date' => '2015-01-01',
        'student_civil_number' => fake()->unique()->numerify('########'),
        'student_state' => 'Muscat',
        'student_governorate' => 'Muscat',
        'student_village' => 'Al Khoud',
        'student_house_number' => '12',
        'student_parents_social_status' => 'married',
        'relationship_with_guardian' => GuardianRelationship::Father,

        'father_name' => 'Father '.fake()->unique()->numerify('####'),
        'father_phone' => fake()->unique()->numerify('9#######'),
        'father_email' => fake()->unique()->safeEmail(),
        'father_id_number' => fake()->unique()->numerify('########'),
        'father_occupation' => 'Engineer',
        'father_work_address' => 'Muscat',
        'father_work_phone' => fake()->unique()->numerify('2#######'),
        'father_is_guardian' => true,

        'mother_name' => 'Mother '.fake()->unique()->numerify('####'),
        'mother_phone' => fake()->unique()->numerify('9#######'),
        'mother_email' => fake()->unique()->safeEmail(),
        'mother_id_number' => fake()->unique()->numerify('########'),
        'mother_occupation' => 'Teacher',
        'mother_work_address' => 'Muscat',
        'mother_work_phone' => fake()->unique()->numerify('2#######'),
        'mother_is_guardian' => false,

        'relative_name' => 'Relative '.fake()->unique()->numerify('####'),
        'relative_phone' => fake()->unique()->numerify('9#######'),
        'relative_email' => fake()->unique()->safeEmail(),
        'relative_id_number' => fake()->unique()->numerify('########'),
        'relative_occupation' => 'Driver',
        'relative_work_address' => 'Muscat',
        'relative_work_phone' => fake()->unique()->numerify('2#######'),
    ], $overrides);
}

it('creates one lead and one application, linked, in AwaitingRegistrationFee', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data);

    expect(Lead::count())->toBe(1)
        ->and(Application::count())->toBe(1)
        ->and($application->lead_id)->toBe(Lead::first()->id)
        ->and($application->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('agrees on branch, program, season, and source metadata between the lead and the application', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data);
    $lead = Lead::find($application->lead_id);

    expect($application->branch_id)->toBe($branch->id)
        ->and($application->program_id)->toBe($program->id)
        ->and($application->source)->toBe(Source::DASHBOARD)
        ->and($lead->branch_id)->toBe($branch->id)
        ->and($lead->program_id)->toBe($program->id)
        ->and($lead->season_id)->toBe($application->season_id)
        ->and($lead->source)->toBe(Source::DASHBOARD);
});

it('derives the lead guardian from whichever party is marked as guardian', function (string $guardianPrefix, array $flags) {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program, $flags);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data);
    $lead = Lead::find($application->lead_id);

    expect($lead->guardian_name)->toBe($data["{$guardianPrefix}_name"])
        ->and($lead->whatsapp)->not->toBeEmpty();
})->with([
    'father is guardian' => ['father', ['father_is_guardian' => true, 'mother_is_guardian' => false]],
    'mother is guardian' => ['mother', ['father_is_guardian' => false, 'mother_is_guardian' => true]],
    'relative is guardian' => ['relative', ['father_is_guardian' => false, 'mother_is_guardian' => false]],
]);

it('rolls back the newly created lead when application creation genuinely fails', function () {
    app()->bind(CreateApplicationAction::class, fn () => new class extends CreateApplicationAction
    {
        public function execute(CreateApplicationDTO $dto): Application
        {
            throw new RuntimeException('forced application failure');
        }
    });

    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data))
        ->toThrow(RuntimeException::class, 'forced application failure');

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('creates nothing and rolls back lead merge updates when deduplication resolves to an already-converted lead', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);

    $firstApplication = app(CreateLeadWithApplicationAction::class)->execute($data);
    $lead = Lead::find($firstApplication->lead_id);
    $originalGuardianName = $lead->guardian_name;

    // Same guardian phone, program, branch (-> same season) and an exact student-name match
    // resolves to the very same lead via the duplicate resolver's identity fingerprint.
    // Give it a longer father name, which the merge would otherwise prefer and persist.
    $secondData = manualEntryData($branch, $program, [
        'student_name' => $data['student_name'],
        'father_phone' => $data['father_phone'],
        'father_name' => $data['father_name'].' A Much Longer Guardian Name',
    ]);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($secondData))
        ->toThrow(LeadAlreadyConvertedException::class);

    expect(Lead::count())->toBe(1)
        ->and(Application::count())->toBe(1)
        ->and($lead->fresh()->guardian_name)->toBe($originalGuardianName);
});

it('creates neither the lead nor the application when the program is unavailable in the branch', function () {
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    // Deliberately not attached to $branch.

    $data = manualEntryData($branch, $program);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data))
        ->toThrow(ProgramNotAvailableInBranchException::class);

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('creates applications through the Filament create page via the composite action', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);

    $page = new class extends CreateApplication
    {
        public function createRecordFromTrait(array $data): Application
        {
            return $this->handleRecordCreation($data);
        }
    };

    $application = $page->createRecordFromTrait($data);

    expect($application)->toBeInstanceOf(Application::class)
        ->and($application->lead_id)->not->toBeNull()
        ->and(Lead::find($application->lead_id))->not->toBeNull()
        ->and(Application::count())->toBe(1)
        ->and(Lead::count())->toBe(1);
});

it('never allows constructing a CreateApplicationDTO with a null lead_id', function () {
    $property = new ReflectionProperty(CreateApplicationDTO::class, 'lead_id');
    $type = $property->getType();

    expect($type)->not->toBeNull()
        ->and($type->allowsNull())->toBeFalse()
        ->and((string) $type)->toBe('int');

    $method = new ReflectionMethod(CreateApplicationDTO::class, 'fromFormData');
    $leadIdParameter = $method->getParameters()[1];

    expect($leadIdParameter->getName())->toBe('leadId')
        ->and($leadIdParameter->getType()->allowsNull())->toBeFalse()
        ->and($leadIdParameter->isOptional())->toBeFalse();
});
