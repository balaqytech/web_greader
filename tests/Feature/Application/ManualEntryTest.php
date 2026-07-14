<?php

use App\Actions\Applications\CreateApplicationAction;
use App\Actions\Leads\CreateLeadAction;
use App\Actions\Leads\CreateLeadWithApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\ProgramType;
use App\Enums\Source;
use App\Exceptions\LeadAlreadyConvertedException;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Models\Affiliate;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\Models\User;
use App\States\Applications\AwaitingRegistrationFee;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function createAvailableBranchAndProgram(): array
{
    $branch = Branch::factory()->create();
    $program = Program::factory()->create();
    $program->branches()->attach($branch, ['price' => 100]);

    return [$branch, $program];
}

/**
 * Deterministic, always-valid Omani local numbers (8 digits, "91" + an incrementing
 * counter). A prior version used fake()->numerify('9#######'), which could occasionally
 * generate a value starting with the bare "968" country-code prefix — normalize_phone_number()
 * rejects that shape (no leading '+' or '0'), causing a rare, non-deterministic test failure.
 * Starting at 91000000 keeps every generated value far outside the 968xxxxx range for any
 * realistic test-suite call volume.
 */
function nextDeterministicPhone(): string
{
    static $counter = 91000000;

    return (string) $counter++;
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
        'father_phone' => nextDeterministicPhone(),
        'father_email' => fake()->unique()->safeEmail(),
        'father_id_number' => fake()->unique()->numerify('########'),
        'father_occupation' => 'Engineer',
        'father_work_address' => 'Muscat',
        'father_work_phone' => nextDeterministicPhone(),
        'father_is_guardian' => true,

        'mother_name' => 'Mother '.fake()->unique()->numerify('####'),
        'mother_phone' => nextDeterministicPhone(),
        'mother_email' => fake()->unique()->safeEmail(),
        'mother_id_number' => fake()->unique()->numerify('########'),
        'mother_occupation' => 'Teacher',
        'mother_work_address' => 'Muscat',
        'mother_work_phone' => nextDeterministicPhone(),
        'mother_is_guardian' => false,

        'relative_name' => 'Relative '.fake()->unique()->numerify('####'),
        'relative_phone' => nextDeterministicPhone(),
        'relative_email' => fake()->unique()->safeEmail(),
        'relative_id_number' => fake()->unique()->numerify('########'),
        'relative_occupation' => 'Driver',
        'relative_work_address' => 'Muscat',
        'relative_work_phone' => nextDeterministicPhone(),
    ], $overrides);
}

/**
 * A user genuinely authorized to create applications in $branchId (null => central/
 * branchless, full access).
 */
function authorizedManualEntryUser(?int $branchId = null): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);

    $permission = Permission::firstOrCreate(['name' => 'Create:Application', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('creates one lead and one application, linked, in AwaitingRegistrationFee', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    $user = authorizedManualEntryUser($branch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);

    expect(Lead::count())->toBe(1)
        ->and(Application::count())->toBe(1)
        ->and($application->lead_id)->toBe(Lead::first()->id)
        ->and($application->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('agrees on branch, program, season, and source metadata between the lead and the application', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    $user = authorizedManualEntryUser($branch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
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
    $user = authorizedManualEntryUser($branch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
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
    $user = authorizedManualEntryUser($branch->id);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(RuntimeException::class, 'forced application failure');

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('creates nothing and rolls back lead merge updates when deduplication resolves to an already-converted lead', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    $user = authorizedManualEntryUser($branch->id);

    $firstApplication = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
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

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($secondData, $user))
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
    $user = authorizedManualEntryUser($branch->id);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(ProgramNotAvailableInBranchException::class);

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('creates applications through the Filament create page via the composite action', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    Auth::login(authorizedManualEntryUser($branch->id));

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

it('preserves an existing website/affiliate lead\'s original attribution and unrelated data through conversion', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $season = Season::current($program->type);
    $affiliate = Affiliate::factory()->create(['code' => 'AFF1']);

    // An unconverted lead that originally arrived from the website via an affiliate, carrying
    // extra tracking data unrelated to the manual-entry form.
    $existingLead = Lead::factory()->create([
        'whatsapp' => '+96891234567',
        'student_name' => 'أحمد محمد الهادي',
        'guardian_name' => 'Original Guardian',
        'program_id' => $program->id,
        'branch_id' => $branch->id,
        'season_id' => $season->id,
        'source' => Source::WEBSITE,
        'affiliate_id' => $affiliate->id,
        'affiliate_code_snapshot' => $affiliate->code,
        'data' => ['utm_campaign' => 'summer-2026'],
    ]);

    $data = manualEntryData($branch, $program, [
        // Same guardian phone (normalizes to +96891234567, matching $existingLead->whatsapp)
        // + exact student-name match resolves to $existingLead via the duplicate resolver's
        // identity fingerprint.
        'father_phone' => '091234567',
        'student_name' => $existingLead->student_name,
        'father_is_guardian' => true,
        'mother_is_guardian' => false,
    ]);
    $user = authorizedManualEntryUser($branch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
    $lead = $existingLead->fresh();

    expect(Lead::count())->toBe(1)
        ->and($lead->source)->toBe(Source::WEBSITE)
        ->and($lead->affiliate_id)->toBe($affiliate->id)
        ->and($lead->affiliate_code_snapshot)->toBe($affiliate->code)
        ->and($lead->data)->toHaveKey('utm_campaign', 'summer-2026')
        ->and($application->source)->toBe(Source::WEBSITE)
        ->and($application->affiliate_id)->toBe($affiliate->id);
});

it('never re-resolves the season internally once CreateLeadAction is given one explicitly', function () {
    [$branch, $program] = createAvailableBranchAndProgram();

    // A season of a *different* program type than $program's. If CreateLeadAction ignored
    // the explicit $season argument and fell back to resolving Season::current($program->type)
    // internally, the resulting lead would end up with that (mismatched-type) season instead
    // — this proves the explicitly supplied season is used as-is, never re-resolved.
    $otherTypeSeason = Season::factory()->create([
        'type' => $program->type === ProgramType::Academic ? ProgramType::Summer : ProgramType::Academic,
        'is_active' => true,
    ]);

    $lead = app(CreateLeadAction::class)->execute(
        whatsapp: '+96899000001',
        guardian_name: 'Guardian',
        student_name: 'Student Season Test',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
        season: $otherTypeSeason,
    );

    expect($lead->season_id)->toBe($otherTypeSeason->id);
});

it('never allows the lead and application seasons to diverge through manual entry', function () {
    [$branch, $program] = createAvailableBranchAndProgram();
    $data = manualEntryData($branch, $program);
    $user = authorizedManualEntryUser($branch->id);

    $expectedSeason = Season::current($program->type);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
    $lead = Lead::find($application->lead_id);

    expect($lead->season_id)->toBe($expectedSeason->id)
        ->and($application->season_id)->toBe($expectedSeason->id);
});
