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
        $data['whatsapp'] = $this->formatWhatsapp($data['whatsapp']);

        try {
            $submission = ReadingAssessmentFormSubmission::updateOrCreate($data);
        } catch (QueryException $e) {
            if ($e->getCode() == 23000) {
                throw new DuplicateSubmissionException;
            }
        }

        return $submission;
    }

    private function formatWhatsapp(string $whatsapp): string
    {
        $whatsapp = convert_eastern_arabic_to_arabic($whatsapp);
        $whatsapp = normalize_phone_number($whatsapp);

        return $whatsapp;
    }
}
