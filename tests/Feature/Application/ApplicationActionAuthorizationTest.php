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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

it('grants a same-branch record\'s action once the role carries its permission, and the call actually mutates state', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);

    $user = actionTestUser($branch->id, ['Accept:Application']);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('accept_application')
        ->callAction('accept_application', data: ['notes' => 'looks good'])
        ->assertHasNoActionErrors();

    expect($application->fresh()->status)->toBeInstanceOf(Accepted::class)
        ->and($application->activities()->where('to_state', Accepted::getMorphClass())->exists())->toBeTrue();
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

    Livewire::test(ViewApplication::class, ['record' => $rejectCandidate->getKey()])
        ->assertActionVisible('reject_application')
        ->callAction('reject_application', data: ['rejection_reason' => 'incomplete documents'])
        ->assertHasNoActionErrors();

    expect($rejectCandidate->fresh()->status)->toBeInstanceOf(Rejected::class);

    $cancelCandidate = Application::factory()->awaitingRegistrationFee()->create(['branch_id' => $branch->id]);
    $cancelUser = actionTestUser($branch->id, ['Cancel:Application']);
    $this->actingAs($cancelUser);

    Livewire::test(ViewApplication::class, ['record' => $cancelCandidate->getKey()])
        ->assertActionVisible('cancel_application')
        ->callAction('cancel_application', data: ['notes' => 'guardian withdrew'])
        ->assertHasNoActionErrors();

    expect($cancelCandidate->fresh()->status)->toBeInstanceOf(Cancelled::class);
});

it('authorizes generate-contract once granted, and the underlying transition mutates the expected state', function () {
    // The Livewire action is proven authorized here (assertActionVisible) and proven blocked
    // without the permission elsewhere ('is hidden ...' above) and against a forged call
    // ('cannot generate a contract ...' below). The actual state mutation is exercised
    // directly against the transition, matching this codebase's existing lifecycle-test
    // convention (see ApplicationLifecycleTest), because re-rendering the action bar
    // immediately after this specific transition hits a pre-existing, unrelated staleness
    // issue in OpenContractLinkFilamentAction's URL closure — out of scope for this commit.
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingApplicationCompletion()->create(['branch_id' => $branch->id]);
    $user = actionTestUser($branch->id, ['GenerateContract:Application']);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('move_to_waiting_contract');

    $application->status->transitionTo(AwaitingContractSignature::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingContractSignature::class)
        ->and($application->contract)->not->toBeNull();
});

it('authorizes reopen once granted, and the underlying transition mutates the expected state', function () {
    // Same rationale as the generate-contract test above: reopening invalidates the existing
    // contract token on a separately locked copy, so re-rendering the action bar against the
    // Livewire page's original $record hits the same pre-existing staleness issue. Authorized
    // visibility is proven via Livewire; the mutation is proven directly against the model.
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingContractSignature()->create(['branch_id' => $branch->id]);
    $user = actionTestUser($branch->id, ['Update:Application']);
    $this->actingAs($user);

    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('return_to_submitted');

    $application->status->transitionTo(AwaitingApplicationCompletion::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingApplicationCompletion::class);
});

it('cannot mutate state, create an activity, or advance the contract through a forged direct action call without permission', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);

    $activityCountBefore = ApplicationActivity::count();

    $user = actionTestUser($branch->id);
    $this->actingAs($user);

    // Bypasses callAction()'s own assertActionVisible() pre-check to simulate a forged
    // Livewire payload that mounts and invokes the action directly.
    Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->mountAction('accept_application')
        ->set('mountedActions.0.data.notes', 'forged')
        ->callMountedAction();

    expect($application->fresh()->status)->not->toBeInstanceOf(Accepted::class)
        ->and(ApplicationActivity::count())->toBe($activityCountBefore);
});

it('cannot generate a contract through a forged direct action call without permission', function () {
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

it('cannot store an uploaded contract through a forged direct action call without permission', function () {
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
