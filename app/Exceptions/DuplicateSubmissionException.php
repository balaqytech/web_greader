<?php

namespace App\Exceptions;

use Exception;

class DuplicateSubmissionException extends Exception
{
    public function render()
    {
        return response()->json([
            'status' => 'error',
            'code' => 400,
            'message' => __('alerts.reading_assessment_form_submissions.already_exists'),
        ], 400);
    }
}
