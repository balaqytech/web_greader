<?php

declare(strict_types=1);

use App\Actions\Documents\SyncRequiredDocumentsAction;
use App\Enums\DocumentType;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\RelationManagers\DocumentsRelationManager;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\States\Documents\Approved;
use App\States\Documents\Rejected;
use App\States\Documents\Uploaded;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function relationManagerUser(int $branchId, array $permissions): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);
    foreach ([...['ViewAny:Application', 'View:Application'], ...$permissions] as $name) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function applicationWithDocuments(Branch $branch): Application
{
    $application = Application::factory()->awaitingApplicationCompletion()->create(['branch_id' => $branch->id]);
    app(SyncRequiredDocumentsAction::class)->execute($application);

    return $application;
}

function requirementOf(Application $application, DocumentType $type): ApplicationDocument
{
    return ApplicationDocument::withoutGlobalScope(BranchScope::class)
        ->where('application_id', $application->id)
        ->where('type', $type)
        ->first();
}

it('renders the documents relation manager with the requirement rows', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords(ApplicationDocument::withoutGlobalScope(BranchScope::class)
            ->where('application_id', $application->id)->get());
});

it('shows the upload action only to a user who can upload', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])->assertTableActionHidden('upload', $document);
});

it('hides approve and reject until the document is uploaded', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument', 'Review:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->assertTableActionHidden('approve', $document)
        ->assertTableActionHidden('reject', $document);

    $document->status->transitionTo(Uploaded::class);

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->assertTableActionVisible('approve', $document->fresh())
        ->assertTableActionVisible('reject', $document->fresh());
});

it('approves an uploaded document through the relation manager', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);
    $document->status->transitionTo(Uploaded::class);

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument', 'Review:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->callTableAction('approve', $document->fresh())
        ->assertHasNoTableActionErrors();

    expect($document->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('rejects an uploaded document with a reason through the relation manager', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);
    $document->status->transitionTo(Uploaded::class);

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument', 'Review:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->callTableAction('reject', $document->fresh(), data: ['rejection_reason' => 'Illegible scan'])
        ->assertHasNoTableActionErrors();

    expect($document->fresh()->status)->toBeInstanceOf(Rejected::class)
        ->and($document->fresh()->rejection_reason)->toBe('Illegible scan');
});

it('hides the review actions from a user without the review permission even when uploaded', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);
    $document->status->transitionTo(Uploaded::class);

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])->assertTableActionHidden('approve', $document->fresh());
});

it('exposes the history action listing uploaded versions', function () {
    Storage::fake('local');
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);
    ApplicationDocumentFile::factory()->for($document, 'document')->create();

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument', 'View:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->assertTableActionVisible('history', $document)
        ->callTableAction('history', $document)
        ->assertHasNoTableActionErrors();
});
