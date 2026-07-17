<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\DocumentReviewException;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\States\Documents\Approved;
use App\States\Documents\Uploaded;
use Illuminate\Support\Facades\DB;

/**
 * Approves an uploaded document. Under the document's row lock the persisted state is
 * re-verified — only an {@see Uploaded} document may be reviewed — so an approval cannot land
 * on a version a concurrent upload has already superseded. Records who approved and when.
 */
final class ApproveDocumentAction
{
    /**
     * @throws DocumentReviewException
     */
    public function execute(ApplicationDocument $document, User $reviewer): ApplicationDocument
    {
        return DB::transaction(function () use ($document, $reviewer): ApplicationDocument {
            $locked = ApplicationDocument::withoutGlobalScope(BranchScope::class)
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status instanceof Uploaded) {
                throw DocumentReviewException::notUploaded($locked);
            }

            $locked->forceFill([
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $locked->status->transitionTo(Approved::class);

            return $locked;
        }, attempts: 3);
    }
}
