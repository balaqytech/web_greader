<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\States\Documents\DocumentState;
use App\States\Documents\Missing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('creates the three document tables with the expected columns', function () {
    expect(Schema::hasColumns('applications', ['is_transfer_student']))->toBeTrue()
        ->and(Schema::hasColumns('application_documents', [
            'application_id', 'branch_id', 'type', 'status', 'is_required',
            'requirement_group', 'current_file_id', 'reviewed_by', 'reviewed_at', 'rejection_reason',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('application_document_files', [
            'application_document_id', 'file_path', 'original_name', 'mime_type', 'size',
            'uploaded_by_type', 'uploaded_by_id', 'uploaded_at',
        ]))->toBeTrue();
});

it('casts type, status, and is_required', function () {
    $document = ApplicationDocument::factory()->create([
        'type' => DocumentType::Passport,
        'status' => Missing::$name,
        'is_required' => 1,
    ]);

    expect($document->type)->toBeInstanceOf(DocumentType::class)
        ->and($document->status)->toBeInstanceOf(DocumentState::class)
        ->and($document->status)->toBeInstanceOf(Missing::class)
        ->and($document->is_required)->toBeTrue();
});

it('exposes the application, branch, files, current file, and reviewer relations', function () {
    $document = ApplicationDocument::factory()->create();
    $file = ApplicationDocumentFile::factory()->for($document, 'document')->create();
    $document->update(['current_file_id' => $file->id]);

    expect($document->application)->toBeInstanceOf(Application::class)
        ->and($document->branch)->toBeInstanceOf(Branch::class)
        ->and($document->files->pluck('id')->all())->toContain($file->id)
        ->and($document->currentFile->id)->toBe($file->id);
});

it('enforces one row per application and type', function () {
    $application = Application::factory()->create();

    ApplicationDocument::factory()->for($application)->create(['type' => DocumentType::BirthCertificate]);

    expect(fn () => ApplicationDocument::factory()->for($application)->create(['type' => DocumentType::BirthCertificate]))
        ->toThrow(QueryException::class);
});

it('denormalises branch_id onto the document so scope checks never need a join', function () {
    $document = ApplicationDocument::factory()->create();

    expect($document->branch_id)->toBe($document->application->branch_id);
});

it('cascades document and file rows when the application is deleted', function () {
    $application = Application::factory()->create();
    $document = ApplicationDocument::factory()->for($application)->create();
    ApplicationDocumentFile::factory()->for($document, 'document')->create();

    $application->delete();

    expect(ApplicationDocument::withoutGlobalScope(BranchScope::class)->count())->toBe(0)
        ->and(ApplicationDocumentFile::count())->toBe(0);
});

it('treats file history rows as append-only, refusing updates', function () {
    $file = ApplicationDocumentFile::factory()->create();

    expect(fn () => $file->update(['original_name' => 'changed.pdf']))
        ->toThrow(LogicException::class);
});

it('treats file history rows as append-only, refusing model deletes', function () {
    $file = ApplicationDocumentFile::factory()->create();

    expect(fn () => $file->delete())
        ->toThrow(LogicException::class);
});

it('scopes documents to the acting user branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $documentA = ApplicationDocument::factory()->create(['branch_id' => $branchA->id]);
    ApplicationDocument::factory()->create(['branch_id' => $branchB->id]);

    $this->actingAs(User::factory()->create(['branch_id' => $branchA->id]));

    expect(ApplicationDocument::pluck('id')->all())->toBe([$documentA->id]);
});
