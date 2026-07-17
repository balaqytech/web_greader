<?php

declare(strict_types=1);

use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Branch;
use App\Models\User;
use App\States\Applications\AwaitingContractSignature;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function contractActionUser(int $branchId): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);
    foreach (['ViewAny:Application', 'View:Application', 'GenerateContract:Application', 'ViewAny:ApplicationDocument'] as $name) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('still generates the contract when documents are missing — the warning never blocks', function () {
    $branch = Branch::factory()->create();
    $application = Application::factory()->awaitingApplicationCompletion()->create(['branch_id' => $branch->id]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    $this->actingAs(contractActionUser($branch->id));

    $component = Livewire::test(ViewApplication::class, ['record' => $application->getKey()])
        ->assertActionVisible('move_to_waiting_contract')
        ->mountAction('move_to_waiting_contract')
        ->assertSchemaComponentVisible('document-warning');

    $mountedSchema = $component->instance()->getSchema(
        $component->instance()->getMountedActionSchemaName(),
    );
    $warning = $mountedSchema->getFlatComponents(withHidden: true)['document-warning'];

    expect((string) $warning->getHeading())->toBe(__('admin.document.warning.contract_heading'))
        ->and((string) $warning->getDescription())->toContain(__('admin.document.groups.student_identity'));

    $component
        ->set('mountedActions.0.data.notes', 'proceeding')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});
