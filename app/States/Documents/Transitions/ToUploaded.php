<?php

namespace App\States\Documents\Transitions;

use App\Exceptions\DocumentUploadException;
use App\Models\ApplicationDocument;
use App\States\Documents\Uploaded;
use Illuminate\Support\Facades\Storage;
use Spatie\ModelStates\Transition;

final class ToUploaded extends Transition
{
    public function __construct(public ApplicationDocument $document) {}

    public function handle(): ApplicationDocument
    {
        $file = $this->document->currentFile()->first();

        if ($file === null || ! Storage::disk('local')->exists($file->file_path)) {
            throw DocumentUploadException::currentFileRequired($this->document->getKey());
        }

        if ($this->document->reviewed_by !== null || $this->document->reviewed_at !== null || $this->document->rejection_reason !== null) {
            throw DocumentUploadException::staleReviewMetadata($this->document->getKey());
        }

        $this->document->status = Uploaded::class;
        $this->document->save();

        return $this->document;
    }
}
