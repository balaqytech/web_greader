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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
});

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

function markRelationManagerDocumentUploaded(ApplicationDocument $document): ApplicationDocument
{
    $file = ApplicationDocumentFile::factory()->for($document, 'document')->create();
    Storage::disk('local')->put($file->file_path, "%PDF-1.4\nrelation-manager");
    $document->update([
        'current_file_id' => $file->id,
        'reviewed_by' => null,
        'reviewed_at' => null,
        'rejection_reason' => null,
    ]);
    $document->status->transitionTo(Uploaded::class);

    return $document->fresh();
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

    markRelationManagerDocumentUploaded($document);

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
    markRelationManagerDocumentUploaded($document);

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
    markRelationManagerDocumentUploaded($document);

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
    markRelationManagerDocumentUploaded($document);

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument']));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])->assertTableActionHidden('approve', $document->fresh());
});

it('exposes the history action listing uploaded versions', function () {
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

it('uploads a real private file through the relation manager', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);

    $this->actingAs(relationManagerUser($branch->id, [
        'ViewAny:ApplicationDocument',
        'Upload:ApplicationDocument',
    ]));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])
        ->callTableAction('upload', $document, data: [
            'file' => UploadedFile::fake()->createWithContent('birth.pdf', "%PDF-1.4\nrelation upload"),
        ])
        ->assertHasNoTableActionErrors();

    $document->refresh();

    expect($document->status)->toBeInstanceOf(Uploaded::class)
        ->and($document->currentFile)->not->toBeNull()
        ->and($document->currentFile->mime_type)->toBe('application/pdf')
        ->and(Storage::disk('local')->exists($document->currentFile->file_path))->toBeTrue()
        ->and(Storage::disk('local')->allFiles('documents/tmp'))->toBeEmpty();
});

it('reaches the upload callback authorization before touching a forged private path', function () {
    $branch = Branch::factory()->create();
    $application = applicationWithDocuments($branch);
    $document = requirementOf($application, DocumentType::BirthCertificate);
    Storage::disk('local')->put('documents/tmp/forged.pdf', "%PDF-1.4\nforged");

    $this->actingAs(relationManagerUser($branch->id, ['ViewAny:ApplicationDocument']));

    $component = Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewApplication::class,
    ])->instance();
    $action = $component->getTable()->getAction('upload')->record($document);

    expect(fn () => $action->data([
        'file' => 'documents/tmp/forged.pdf',
        'original_name' => 'forged.pdf',
    ])->call())->toThrow(AuthorizationException::class);

    expect(Storage::disk('local')->exists('documents/tmp/forged.pdf'))->toBeTrue()
        ->and($document->fresh()->current_file_id)->toBeNull()
        ->and($document->files()->count())->toBe(0);
});
