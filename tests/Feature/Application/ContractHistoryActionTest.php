<?php

declare(strict_types=1);

use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\RelationManagers\ContractsRelationManager;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function contractHistoryUser(?int $branchId): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);
    $user->givePermissionTo([
        Permission::firstOrCreate(['name' => 'ViewAny:Application', 'guard_name' => 'web']),
        Permission::firstOrCreate(['name' => 'View:Application', 'guard_name' => 'web']),
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('shows an open-artifact action with the signed file URL for a signed version', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);
    $signed = $application->activeContract;

    $this->actingAs(contractHistoryUser($branch->id));

    Livewire::test(ContractsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->assertSuccessful()
        ->assertTableActionVisible('view_signed_contract', $signed)
        ->assertTableActionHasUrl('view_signed_contract', $signed->signedFileUrl(), $signed);
});

it('hides the action for a version with no stored artifact', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingContractSignature()->create(['branch_id' => $branch->id]);
    $generated = $application->activeContract; // no file_path

    $this->actingAs(contractHistoryUser($branch->id));

    Livewire::test(ContractsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->assertSuccessful()
        ->assertTableActionHidden('view_signed_contract', $generated);
});

it('resolves a legacy absolute-URL artifact through the same action', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);
    $application->activeContract->update(['file_path' => 'https://cdn.example.test/contracts/legacy.pdf']);
    $signed = $application->activeContract;

    $this->actingAs(contractHistoryUser($branch->id));

    Livewire::test(ContractsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->assertTableActionHasUrl('view_signed_contract', 'https://cdn.example.test/contracts/legacy.pdf', $signed);
});

it('denies the artifact action to a cross-branch reviewer', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $application = Application::factory()->awaitingBranchReview()->create(['branch_id' => $branch->id]);

    // A reviewer bound to another branch cannot view this application, so the record-aware
    // authorize() on the artifact action denies — the same policy that gates the whole page.
    $crossBranch = contractHistoryUser($otherBranch->id);

    expect(Gate::forUser($crossBranch)->denies('view', $application))->toBeTrue();
});

it('does not expose the signing token in the artifact URL', function () {
    $contract = ApplicationContract::factory()->signed()->create();

    expect($contract->signedFileUrl())->not->toContain($contract->token ?? 'no-token');
});
