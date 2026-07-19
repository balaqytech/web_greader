<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation shape for listing reading-assessment submissions via the service API. Ability and
 * the service-account gate are enforced by route middleware.
 */
class IndexReadingAssessmentRequest extends FormRequest
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
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'whatsapp' => ['nullable', 'string'],
        ];
    }
}
