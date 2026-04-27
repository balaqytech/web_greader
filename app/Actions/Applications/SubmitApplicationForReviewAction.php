<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\States\Applications\DataComplete;
use App\States\Applications\UnderReview;
use Illuminate\Validation\ValidationException;

final class SubmitApplicationForReviewAction
{
    /**
     * Validate required fields, transition to DataComplete, then to UnderReview.
     *
     * @throws ValidationException
     */
    public function execute(Application $application, ?int $transitionedBy = null, ?string $notes = null): Application
    {
        $this->validateRequiredData($application);

        $application->status->transitionTo(DataComplete::class, transitionedBy: $transitionedBy, notes: $notes);
        $application->refresh();

        $application->status->transitionTo(UnderReview::class, transitionedBy: $transitionedBy, notes: $notes);

        return $application->fresh();
    }

    /**
     * @throws ValidationException
     */
    private function validateRequiredData(Application $application): void
    {
        $errors = [];

        // Student data
        $studentFields = [
            'student_name',
            'student_gender',
            'student_birth_date',
            'student_civil_number',
            'student_state',
            'student_governorate',
            'student_village',
            'student_house_number',
            'student_parents_social_status',
        ];

        foreach ($studentFields as $field) {
            if (empty($application->{$field})) {
                $errors[$field] = [__('validation.required', ['attribute' => $field])];
            }
        }

        // Father data
        $fatherFields = [
            'father_name',
            'father_phone',
            'father_id_number',
        ];

        foreach ($fatherFields as $field) {
            if (empty($application->{$field})) {
                $errors[$field] = [__('validation.required', ['attribute' => $field])];
            }
        }

        // Mother data
        $motherFields = [
            'mother_name',
            'mother_phone',
            'mother_id_number',
        ];

        foreach ($motherFields as $field) {
            if (empty($application->{$field})) {
                $errors[$field] = [__('validation.required', ['attribute' => $field])];
            }
        }

        // Relative data
        $relativeFields = [
            'relative_name',
            'relative_phone',
        ];

        foreach ($relativeFields as $field) {
            if (empty($application->{$field})) {
                $errors[$field] = [__('validation.required', ['attribute' => $field])];
            }
        }

        // Guardian flag
        if (! $application->father_is_guardian && ! $application->mother_is_guardian) {
            $errors['guardian'] = [__('validation.required', ['attribute' => 'guardian'])];
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
