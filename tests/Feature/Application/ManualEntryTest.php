<?php

use App\Actions\Applications\CreateApplicationAction;
use App\Actions\Leads\CreateLeadAction;
use App\Actions\Leads\CreateLeadWithApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\ProgramType;
use App\Enums\Source;
use App\Exceptions\InvalidSeasonForProgramException;
use App\Exceptions\LeadAlreadyConvertedException;
use App\Exceptions\ProgramNotAvailableInBranchException;
use App\Models\Affiliate;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\States\Applications\AwaitingRegistrationFee;
use Illuminate\Support\Facades\Auth;
use Tests\Support\ManualEntryFixtures;

it('creates one lead and one application, linked, in AwaitingRegistrationFee', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);

    expect(Lead::count())->toBe(1)
        ->and(Application::count())->toBe(1)
        ->and($application->lead_id)->toBe(Lead::first()->id)
        ->and($application->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('agrees on branch, program, season, and source metadata between the lead and the application', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

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
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program, $flags);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

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

    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(RuntimeException::class, 'forced application failure');

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('creates nothing and rolls back lead merge updates when deduplication resolves to an already-converted lead', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

    $firstApplication = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
    $lead = Lead::find($firstApplication->lead_id);
    $originalGuardianName = $lead->guardian_name;

    // Same guardian phone, program, branch (-> same season) and an exact student-name match
    // resolves to the very same lead via the duplicate resolver's identity fingerprint.
    // Give it a longer father name, which the merge would otherwise prefer and persist.
    $secondData = ManualEntryFixtures::manualEntryData($branch, $program, [
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

    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

    expect(fn () => app(CreateLeadWithApplicationAction::class)->execute($data, $user))
        ->toThrow(ProgramNotAvailableInBranchException::class);

    expect(Lead::count())->toBe(0)
        ->and(Application::count())->toBe(0);
});

it('creates applications through the Filament create page via the composite action', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    Auth::login(ManualEntryFixtures::authorizedManualEntryUser($branch->id));

    $page = ManualEntryFixtures::manualEntryPage();

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
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
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

    $data = ManualEntryFixtures::manualEntryData($branch, $program, [
        // Same guardian phone (normalizes to +96891234567, matching $existingLead->whatsapp)
        // + exact student-name match resolves to $existingLead via the duplicate resolver's
        // identity fingerprint.
        'father_phone' => '091234567',
        'student_name' => $existingLead->student_name,
        'father_is_guardian' => true,
        'mother_is_guardian' => false,
    ]);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

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

it('falls back to the incoming source when a legacy duplicate lead has a null source', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $season = Season::current($program->type);

    // A historical lead with no recorded source at all (nullable column, pre-dates source
    // tracking). Merging into it must never dereference a null source.
    $existingLead = Lead::factory()->create([
        'whatsapp' => '+96891234568',
        'student_name' => 'طالب بلا مصدر',
        'program_id' => $program->id,
        'branch_id' => $branch->id,
        'season_id' => $season->id,
        'source' => null,
        'data' => [],
    ]);

    $data = ManualEntryFixtures::manualEntryData($branch, $program, [
        'father_phone' => '091234568',
        'student_name' => $existingLead->student_name,
        'father_is_guardian' => true,
        'mother_is_guardian' => false,
    ]);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
    $lead = $existingLead->fresh();

    expect($lead->source)->toBe(Source::DASHBOARD)
        ->and($application->source)->toBe(Source::DASHBOARD);
});

it('never lets a duplicate submission overwrite an existing lead\'s colliding attribution data keys', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $season = Season::current($program->type);

    $existingLead = Lead::factory()->create([
        'whatsapp' => '+96891234569',
        'student_name' => 'طالب تعارض البيانات',
        'program_id' => $program->id,
        'branch_id' => $branch->id,
        'season_id' => $season->id,
        'source' => Source::WEBSITE,
        'data' => ['utm_campaign' => 'original-campaign', 'utm_source' => 'original-source'],
    ]);

    $lead = app(CreateLeadAction::class)->execute(
        whatsapp: '091234569',
        guardian_name: 'Duplicate Guardian',
        student_name: $existingLead->student_name,
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
        data: ['utm_campaign' => 'new-attempt-should-be-ignored', 'utm_new_key' => 'should-be-added'],
        season: $season,
    );

    expect($lead->id)->toBe($existingLead->id)
        ->and($lead->data)->toHaveKey('utm_campaign', 'original-campaign')
        ->and($lead->data)->toHaveKey('utm_source', 'original-source')
        ->and($lead->data)->toHaveKey('utm_new_key', 'should-be-added');
});

it('uses an explicitly supplied valid, active, same-type season as-is without substituting a re-resolved one', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();

    // A second active season of the *same* program type, distinct from whatever
    // Season::current($program->type) would resolve on its own — proves the explicitly
    // supplied season is used verbatim, never silently swapped for an internally re-resolved
    // one, as long as it is itself valid.
    $explicitSeason = Season::factory()->create([
        'type' => $program->type,
        'is_active' => true,
    ]);

    $lead = app(CreateLeadAction::class)->execute(
        whatsapp: '+96899000001',
        guardian_name: 'Guardian',
        student_name: 'Student Season Test',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
        season: $explicitSeason,
    );

    expect($lead->season_id)->toBe($explicitSeason->id);
});

it('rejects an explicitly supplied season of the wrong program type with zero writes', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();

    $wrongTypeSeason = Season::factory()->create([
        'type' => $program->type === ProgramType::Academic ? ProgramType::Summer : ProgramType::Academic,
        'is_active' => true,
    ]);

    expect(fn () => app(CreateLeadAction::class)->execute(
        whatsapp: '+96899000002',
        guardian_name: 'Guardian',
        student_name: 'Student Wrong Season Type',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
        season: $wrongTypeSeason,
    ))->toThrow(InvalidSeasonForProgramException::class);

    expect(Lead::count())->toBe(0);
});

it('rejects an explicitly supplied inactive season with zero writes', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();

    $inactiveSeason = Season::factory()->create([
        'type' => $program->type,
        'is_active' => false,
    ]);

    expect(fn () => app(CreateLeadAction::class)->execute(
        whatsapp: '+96899000003',
        guardian_name: 'Guardian',
        student_name: 'Student Inactive Season',
        program_id: $program->id,
        branch_id: $branch->id,
        source: Source::DASHBOARD->value,
        season: $inactiveSeason,
    ))->toThrow(InvalidSeasonForProgramException::class);

    expect(Lead::count())->toBe(0);
});

it('never allows the lead and application seasons to diverge through manual entry', function () {
    [$branch, $program] = ManualEntryFixtures::createAvailableBranchAndProgram();
    $data = ManualEntryFixtures::manualEntryData($branch, $program);
    $user = ManualEntryFixtures::authorizedManualEntryUser($branch->id);

    $expectedSeason = Season::current($program->type);

    $application = app(CreateLeadWithApplicationAction::class)->execute($data, $user);
    $lead = Lead::find($application->lead_id);

    expect($lead->season_id)->toBe($expectedSeason->id)
        ->and($application->season_id)->toBe($expectedSeason->id);
});
