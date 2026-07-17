<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\DocumentReviewException;
use App\Models\ApplicationDocument;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\States\Documents\Rejected;
use App\States\Documents\Uploaded;
use Illuminate\Support\Facades\DB;

/**
 * Rejects an uploaded document with a mandatory reason. Like approval, it re-verifies the
 * persisted {@see Uploaded} state under the row lock so the verdict cannot apply to a version a
 * concurrent upload has replaced. The reason is required — a rejection the guardian cannot act
 * on is useless — and is validated here, not only in the form, so the API is held to it too.
 */
final class RejectDocumentAction
{
    /**
     * @throws DocumentReviewException
     */
    public function execute(ApplicationDocument $document, User $reviewer, string $reason): ApplicationDocument
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw DocumentReviewException::reasonRequired();
        }

        return DB::transaction(function () use ($document, $reviewer, $reason): ApplicationDocument {
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
                'rejection_reason' => $reason,
            ])->save();

            $locked->status->transitionTo(Rejected::class);

            return $locked;
        }, attempts: 3);
    }
}
