<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape-only validation for the verified status-check. Uses the exact same input names as
 * payment initiation (`application_reference`, `guardian_phone`); the actual reference + phone
 * match happens in the shared action so a mismatch answers with one generic 404 rather than
 * revealing which half was wrong. Ability and the service-account gate are route middleware.
 */
class StatusCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_reference' => ['required', 'string', 'max:64'],
            'guardian_phone' => ['required', 'string', 'max:32'],
        ];
    }
}
