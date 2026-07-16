<?php

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\Branch;
use App\Models\User;
use App\States\Applications\Accepted;
use App\States\Applications\AwaitingApplicationCompletion;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\AwaitingRegistrationFee;
use App\States\Applications\Cancelled;
use App\States\Applications\Rejected;
use Database\Seeders\ShieldPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * A user with the base ViewAny/View permissions needed just to open the ViewApplication page
 * for $branchId's records, plus every $extraPermission this specific test is exercising.
 *
 * @param  list<string>  $extraPermissions
 */
function actionTestUser(?int $branchId, array $extraPermissions = []): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);

    foreach ([...['ViewAny:Application', 'View:Application'], ...$extraPermissions] as $name) {
        $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('is hidden when the acting user lacks the action\'s permission, even in the correct state', function (string $actionName, string $permission, string $factoryState) {
    $branch = Branch::factory()->create();
    $application = Application::factory()->{$factoryState}()->create(['branch_id' => $branch->id]);

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionHidden($actionName);
})->with([
    'accept without Accept:Application' => ['accept_application', 'Accept:Application', 'awaitingBranchReview'],
    'reject without Reject:Application' => ['reject_application', 'Reject:Application', 'awaitingBranchReview'],
    'cancel without Cancel:Application' => ['cancel_application', 'Cancel:Application', 'awaitingRegistrationFee'],
    'generate contract without GenerateContract:Application' => ['move_to_waiting_contract', 'GenerateContract:Application', 'awaitingApplicationCompletion'],
    'reopen without Update:Application' => ['return_to_submitted', 'Update:Application', 'awaitingContractSignature'],
    'upload contract without UploadSignedContract:Application' => ['upload_contract', 'UploadSignedContract:Application', 'awaitingContractSignature'],
]);

it('grants a same-branch record\'s action once the permission is granted directly, and the call actually mutates state', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);

    $user = actionTestUser($branch->id, ['Accept:Application']);
    $this->actingAs($user);

    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('accept_application')
        ->callAction('accept_application', data: ['notes' => 'looks good'])
        ->assertHasNoActionErrors();

    expect($application->fresh()->status)->toBeInstanceOf(Accepted::class)
        ->and($application->activities()->where('to_state', Accepted::getMorphClass())->exists())->toBeTrue()
        ->and($component->instance()->getRecord()->status)->toBeInstanceOf(Accepted::class);
});

it('still denies the same permission on a cross-branch record', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $otherBranch->id]);

    $user = actionTestUser($ownBranch->id, ['Accept:Application', 'View:Application']);
    $this->actingAs($user);

    // The resource's route-binding query resolves the record (Filament's own query for this
    // isn't branch-scoped), but the view policy then denies it — proving the branch check,
    // not just the action-level permission, is load-bearing.
    $this->get(ApplicationResource::getUrl('view', ['record' => $application]))
        ->assertForbidden();
});

it('lets ViewAllBranches:Application permit accept on a cross-branch record the branch check would otherwise deny', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $otherBranch->id]);

    $user = actionTestUser($ownBranch->id, ['Accept:Application', 'ViewAllBranches:Application']);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('accept_application')
        ->callAction('accept_application', data: ['notes' => 'cross branch ok'])
        ->assertHasNoActionErrors();

    expect($application->fresh()->status)->toBeInstanceOf(Accepted::class);
});

it('denies reject and cancel without their respective permissions in their otherwise-correct states', function () {
    $branch = Branch::factory()->create();
    $rejectCandidate = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);
    $cancelCandidate = Application::factory()->awaitingRegistrationFee()->create(['branch_id' => $branch->id]);

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $rejectCandidate->getKey()])
        ->assertActionHidden('reject_application');

    Livewire::test(ViewApplication::class, ['record' => $cancelCandidate->getKey()])
        ->assertActionHidden('cancel_application');

    expect($rejectCandidate->fresh()->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($cancelCandidate->fresh()->status)->toBeInstanceOf(AwaitingRegistrationFee::class);
});

it('authorizes reject and cancel once granted, and each mutates the expected state', function () {
    $branch = Branch::factory()->create();

    $rejectCandidate = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);
    $rejectUser = actionTestUser($branch->id, ['Reject:Application']);
    $this->actingAs($rejectUser);

    $rejectComponent = Livewire::test(ViewApplication::class, ['record' => $rejectCandidate->getKey()])
        ->assertActionVisible('reject_application')
        ->callAction('reject_application', data: ['rejection_reason' => 'incomplete documents'])
        ->assertHasNoActionErrors();

    expect($rejectCandidate->fresh()->status)->toBeInstanceOf(Rejected::class)
        ->and($rejectComponent->instance()->getRecord()->status)->toBeInstanceOf(Rejected::class);

    $cancelCandidate = Application::factory()->awaitingRegistrationFee()->create(['branch_id' => $branch->id]);
    $cancelUser = actionTestUser($branch->id, ['Cancel:Application']);
    $this->actingAs($cancelUser);

    $cancelComponent = Livewire::test(ViewApplication::class, ['record' => $cancelCandidate->getKey()])
        ->assertActionVisible('cancel_application')
        ->callAction('cancel_application', data: ['notes' => 'guardian withdrew'])
        ->assertHasNoActionErrors();

    expect($cancelCandidate->fresh()->status)->toBeInstanceOf(Cancelled::class)
        ->and($cancelComponent->instance()->getRecord()->status)->toBeInstanceOf(Cancelled::class);
});

it('authorizes generate-contract once granted, runs the real Livewire action end to end, and rerenders the contract actions', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingApplicationCompletion()->create(['branch_id' => $branch->id]);
    $user = actionTestUser($branch->id, ['GenerateContract:Application', 'UploadSignedContract:Application']);
    $this->actingAs($user);

    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('move_to_waiting_contract')
        ->callAction('move_to_waiting_contract', data: ['notes' => 'contract ready'])
        ->assertHasNoActionErrors();

    // Proves the fix in App\Filament\Resources\Applications\Actions\Concerns\RefreshesApplicationRecord:
    // the transition returns a separately locked Application instance, and the Livewire page's
    // own record must be synced to it (state + the newly created contract relation) before the
    // action bar rerenders — otherwise these contract actions render against the stale
    // pre-transition record and either stay hidden or crash on a null contract.
    $component->assertActionVisible('open_contract_link')
        ->assertActionVisible('copy_contract_link')
        ->assertActionVisible('upload_contract');

    $record = $component->instance()->getRecord();

    expect($record->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($record->contract)->not->toBeNull()
        ->and($record->contract->token)->not->toBeNull();

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->contract)->not->toBeNull()
        ->and($application->contract->token)->not->toBeNull();
});

it('authorizes reopen once granted, runs the real Livewire action end to end, and invalidates the old contract token', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingContractSignature()->create(['branch_id' => $branch->id]);
    $originalToken = $application->contract->token;
    $user = actionTestUser($branch->id, ['Update:Application', 'GenerateContract:Application']);
    $this->actingAs($user);

    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('return_to_submitted')
        ->callAction('return_to_submitted', data: ['notes' => 'needs correction'])
        ->assertHasNoActionErrors();

    // Same rationale as the generate-contract test above: the reopen transition invalidates the
    // contract token on a separately locked copy, so the action bar must be driven by the
    // synced record — generate-contract becomes available again and the contract-signing
    // actions (which require AwaitingContractSignature) must disappear.
    $component->assertActionVisible('move_to_waiting_contract')
        ->assertActionHidden('open_contract_link')
        ->assertActionHidden('copy_contract_link')
        ->assertActionHidden('upload_contract');

    $record = $component->instance()->getRecord();

    expect($record->status)->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and($originalToken)->not->toBeNull();

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and($application->contract->token)->toBeNull();
});

it('blocks accept_application at Filament\'s own mount-stage authorization gate before the callback runs, when permission is missing', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);

    $activityCountBefore = ApplicationActivity::count();

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    // mountAction()'s own isDisabled() check (which folds in isAuthorized()) rejects the action
    // before mounting, so mountedActions is emptied and callMountedAction() below is a no-op —
    // this proves the presentation-layer gate, not the closure's own Gate::authorize(); see the
    // 'reaches the closure's own Gate::authorize()' test below for that distinct guarantee.
    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->mountAction('accept_application')
        ->set('mountedActions.0.data.notes', 'forged')
        ->callMountedAction();

    expect($application->fresh()->status)->not->toBeInstanceOf(Accepted::class)
        ->and(ApplicationActivity::count())->toBe($activityCountBefore);
});

it('blocks move_to_waiting_contract at Filament\'s own mount-stage authorization gate before the callback runs, when permission is missing', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingApplicationCompletion()->create(['branch_id' => $branch->id]);

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->mountAction('move_to_waiting_contract')
        ->callMountedAction();

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingApplicationCompletion::class)
        ->and($application->fresh()->contract)->toBeNull();
});

it('blocks upload_contract at Filament\'s own mount-stage authorization gate before the callback runs, when permission is missing', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingContractSignature()->create(['branch_id' => $branch->id]);

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->mountAction('upload_contract')
        ->callMountedAction();

    expect($application->fresh()->contract->file_path)->toBeNull()
        ->and($application->fresh()->contract->signed_at)->toBeNull();
});

it('reaches the closure\'s own Gate::authorize() and throws AuthorizationException when Filament\'s mount-stage gate is bypassed entirely', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);

    $activityCountBefore = ApplicationActivity::count();

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    // getAction() resolves the registered Action object directly by name, skipping
    // mountAction()'s own isDisabled()/isAuthorized() gate entirely (unlike mountAction(), which
    // the tests above prove already blocks unauthorized calls before any callback runs). Calling
    // ->call() on it goes straight into the action's closure, so this is the only way to prove
    // the closure's own `Gate::authorize('accept', $record)` line is itself reached and enforced,
    // independent of the presentation-layer gate.
    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])->instance();
    $action = $component->getAction('accept_application');

    expect(fn () => $action->data(['notes' => 'forged'])->call())
        ->toThrow(AuthorizationException::class);

    expect($application->fresh()->status)->not->toBeInstanceOf(Accepted::class)
        ->and(ApplicationActivity::count())->toBe($activityCountBefore);
});

it('reaches upload_contract\'s own Gate::authorize() before any storage or domain mutation, when Filament\'s mount-stage gate is bypassed', function () {
    Storage::fake('public');

    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingContractSignature()->create(['branch_id' => $branch->id]);
    $originalFilePath = $application->contract->file_path;
    $originalSignedAt = $application->contract->signed_at;

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    // Simulates the file Filament's FileUpload component would already have placed on the public
    // disk before form submission, using the documented fake-upload testing API.
    $storedPath = UploadedFile::fake()
        ->create('signed-contract.pdf', 100, 'application/pdf')
        ->store('contracts/uploads', 'public');

    Storage::disk('public')->assertExists($storedPath);

    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])->instance();
    $action = $component->getAction('upload_contract');

    expect(fn () => $action->data(['contract_file' => $storedPath])->call())
        ->toThrow(AuthorizationException::class);

    // Gate::authorize() is the first line of the closure, before UploadSignedContractAction is
    // even constructed — so the domain/storage side effects it would perform never happen.
    $application->refresh();

    expect($application->contract->file_path)->toBe($originalFilePath)
        ->and($application->contract->signed_at)->toBe($originalSignedAt)
        ->and($application->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('lets branch_staff accept an application through the real Filament action purely via role-carried permissions, with no direct permission grant', function () {
    $this->seed(ShieldPermissionSeeder::class);

    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $role = Role::where('name', 'branch_staff')->where('guard_name', 'web')->firstOrFail();
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->permissions()->count())->toBe(0)
        ->and($user->can('Accept:Application'))->toBeTrue();

    $this->actingAs($user);

    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('accept_application')
        ->callAction('accept_application', data: ['notes' => 'approved via role'])
        ->assertHasNoActionErrors();

    expect($application->fresh()->status)->toBeInstanceOf(Accepted::class)
        ->and($application->activities()->where('to_state', Accepted::getMorphClass())->exists())->toBeTrue()
        ->and($component->instance()->getRecord()->status)->toBeInstanceOf(Accepted::class);
});

it('wires each custom action\'s authorize() call to the expected record-aware policy ability, independent of the action\'s own visibility state', function (string $actionName, string $ability, ?string $permission) {
    $branch = Branch::factory()->create();

    // AwaitingApplicationCompletion so the 'update'-family abilities also pass their own
    // isEditableState() check, with a contract attached regardless so
    // CopyContractLinkFilamentAction's schema — which dereferences $record->contract
    // unconditionally when Filament resolves/mounts the action — can be built without a
    // null-contract crash. getAction() below resolves the action (and its schema) regardless of
    // the action's own visibility, unlike normal page rendering.
    $application = Application::factory()->awaitingApplicationCompletion()->create(['branch_id' => $branch->id]);
    $application->contract()->create([
        'token' => Str::random(64),
        'token_expires_at' => now()->addDays(7),
    ]);

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    $getAuthorized = function () use ($application, $actionName): bool {
        $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])->instance();

        return $component->getAction($actionName)->isAuthorized();
    };

    // No dedicated permission granted yet: authorized only if the ability is one the baseline
    // ViewAny/View:Application grant already satisfies (open/copy contract link, both 'view').
    expect($getAuthorized())->toBe($permission === null);

    if ($permission === null) {
        return;
    }

    $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($getAuthorized())->toBeTrue();
})->with([
    'accept_application maps to the accept ability' => ['accept_application', 'accept', 'Accept:Application'],
    'reject_application maps to the reject ability' => ['reject_application', 'reject', 'Reject:Application'],
    'cancel_application maps to the cancel ability' => ['cancel_application', 'cancel', 'Cancel:Application'],
    'move_to_waiting_contract maps to the generateContract ability' => ['move_to_waiting_contract', 'generateContract', 'GenerateContract:Application'],
    'return_to_submitted maps to the reopen ability' => ['return_to_submitted', 'reopen', 'Update:Application'],
    // submit_application is deliberately absent: the action was removed. It advanced an
    // application past the registration-fee gate on nothing but `Update:Application`, a
    // permission every branch staffer holds, and was inert only because the transition was
    // unregistered. The fee gate is now crossed solely by a paid registration-fee payment —
    // see tests/Feature/Payment/RegistrationFeeGateTest.php.
    'upload_contract maps to the uploadSignedContract ability' => ['upload_contract', 'uploadSignedContract', 'UploadSignedContract:Application'],
    'open_contract_link maps to the view ability, already satisfied by baseline page access' => ['open_contract_link', 'view', null],
    'copy_contract_link maps to the view ability, already satisfied by baseline page access' => ['copy_contract_link', 'view', null],
]);
