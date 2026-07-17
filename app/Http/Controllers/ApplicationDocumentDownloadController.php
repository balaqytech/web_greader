<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicationDocumentFile;
use App\Models\Scopes\BranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a document file version to an authenticated staff member. Files live on the private
 * `local` disk and are never exposed through a public URL — the only way to a file is through
 * this authorized, streamed response.
 *
 * A single history row is addressed, so both the current file and any older version download
 * through the same path without the request ever mutating a row. Authorization is against the
 * *parent document* (view + own-branch), and the file's document is resolved with BranchScope
 * bypassed so the check is the policy's to make, not the query's: a caller outside the branch
 * gets an indistinguishable 404, never a leak of whether the record exists.
 */
class ApplicationDocumentDownloadController extends Controller
{
    public function __invoke(ApplicationDocumentFile $file): StreamedResponse|RedirectResponse
    {
        $document = $file->document()->withoutGlobalScope(BranchScope::class)->firstOrFail();

        if (Gate::denies('view', $document)) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($file->file_path)) {
            abort(404);
        }

        return $disk->download($file->file_path, $file->original_name);
    }
}
