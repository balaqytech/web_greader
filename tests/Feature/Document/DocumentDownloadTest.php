<?php

declare(strict_types=1);

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
});

function documentViewer(int $branchId): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'View:ApplicationDocument', 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function storedFile(ApplicationDocument $document): ApplicationDocumentFile
{
    $path = "documents/applications/{$document->application_id}/{$document->id}/".fake()->uuid().'.pdf';
    Storage::disk('local')->put($path, 'PDF-BYTES');

    return ApplicationDocumentFile::factory()->for($document, 'document')->create(['file_path' => $path]);
}

it('streams the current file to an authorized own-branch viewer', function () {
    $branch = Branch::factory()->create();
    $document = ApplicationDocument::factory()->create(['branch_id' => $branch->id]);
    $file = storedFile($document);

    $this->actingAs(documentViewer($branch->id))
        ->get(route('application-documents.files.download', ['file' => $file->id]))
        ->assertOk()
        ->assertDownload($file->original_name);
});

it('streams an older history version through the same route without changing rows', function () {
    $branch = Branch::factory()->create();
    $document = ApplicationDocument::factory()->create(['branch_id' => $branch->id]);
    $old = storedFile($document);
    $current = storedFile($document);
    $document->update(['current_file_id' => $current->id]);

    $this->actingAs(documentViewer($branch->id))
        ->get(route('application-documents.files.download', ['file' => $old->id]))
        ->assertOk();

    expect($old->fresh()->file_path)->toBe($old->file_path);
});

it('returns 404 when the stored file is missing', function () {
    $branch = Branch::factory()->create();
    $document = ApplicationDocument::factory()->create(['branch_id' => $branch->id]);
    $file = ApplicationDocumentFile::factory()->for($document, 'document')->create(['file_path' => 'documents/gone.pdf']);

    $this->actingAs(documentViewer($branch->id))
        ->get(route('application-documents.files.download', ['file' => $file->id]))
        ->assertNotFound();
});

it('returns 404 for a cross-branch file even loaded via an unscoped record', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $document = ApplicationDocument::factory()->create(['branch_id' => $branchB->id]);
    $file = storedFile($document);

    $this->actingAs(documentViewer($branchA->id))
        ->get(route('application-documents.files.download', ['file' => $file->id]))
        ->assertNotFound();
});

it('refuses an unauthenticated download', function () {
    $document = ApplicationDocument::factory()->create();
    $file = storedFile($document);

    $this->get(route('application-documents.files.download', ['file' => $file->id]))
        ->assertStatus(401);
});
