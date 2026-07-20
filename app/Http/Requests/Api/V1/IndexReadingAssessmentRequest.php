<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ValidPhoneNumber;
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

    protected function prepareForValidation(): void
    {
        $value = $this->input('whatsapp');

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            $this->merge([
                'whatsapp' => normalize_phone_number(convert_eastern_arabic_to_arabic($value)),
            ]);
        } catch (\InvalidArgumentException) {
            // Keep the submitted value so ValidPhoneNumber returns a validation error.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'whatsapp' => ['nullable', 'string', 'max:16', new ValidPhoneNumber],
        ];
    }
}
