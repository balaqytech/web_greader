<?php

namespace App\Actions\ReadingAssessment;

use App\Exceptions\DuplicateSubmissionException;
use App\Models\ReadingAssessmentFormSubmission;
use Illuminate\Database\QueryException;

class CreateSubmission
{
    /**
     * Create a new submission.
     */
    public function execute(array $data): ReadingAssessmentFormSubmission
    {
        $data['whatsapp'] = normalize_phone_number($data['whatsapp']);

        try {
            $submission = ReadingAssessmentFormSubmission::updateOrCreate($data);
        } catch (QueryException $e) {
            if ($e->getCode() == 23000) {
                throw new DuplicateSubmissionException();
            }
        }

        return $submission;
    }
}
