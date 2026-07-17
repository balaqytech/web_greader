<?php

namespace App\States\Documents\Transitions;

use App\Exceptions\DocumentReviewException;
use App\Models\ApplicationDocument;
use App\States\Documents\Approved;
use Illuminate\Support\Facades\Storage;
use Spatie\ModelStates\Transition;

final class UploadedToApproved extends Transition
{
    public function __construct(public ApplicationDocument $document) {}

    public function handle(): ApplicationDocument
    {
        $file = $this->document->currentFile()->first();

        if ($file === null || ! Storage::disk('local')->exists($file->file_path)) {
            throw DocumentReviewException::currentFileRequired($this->document);
        }

        if ($this->document->reviewed_by === null || $this->document->reviewed_at === null || $this->document->rejection_reason !== null) {
            throw DocumentReviewException::incompleteDecision($this->document);
        }

        $this->document->status = Approved::class;
        $this->document->save();

        return $this->document;
    }
}
