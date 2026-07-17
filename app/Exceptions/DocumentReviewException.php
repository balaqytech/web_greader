<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\ApplicationDocument;
use App\States\Documents\Uploaded;
use Exception;

/**
 * A review decision (approve/reject) could not be applied. Only an {@see Uploaded}
 * document may be reviewed, and a rejection must carry a reason; both are enforced under the
 * document's row lock so a decision cannot be applied against a version that has since been
 * replaced.
 */
class DocumentReviewException extends Exception
{
    private function __construct(
        string $message,
        public readonly string $translationKey,
    ) {
        parent::__construct($message);
    }

    public static function notUploaded(ApplicationDocument $document): self
    {
        return new self(
            sprintf(
                'Document %d is %s, not uploaded; only an uploaded document can be reviewed.',
                $document->getKey(),
                $document->status::$name,
            ),
            'admin.document.messages.not_reviewable',
        );
    }

    public static function reasonRequired(): self
    {
        return new self(
            'A rejection reason is required.',
            'admin.document.messages.rejection_reason_required',
        );
    }
}
