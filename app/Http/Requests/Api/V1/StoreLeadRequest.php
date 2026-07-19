<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation shape for lead creation via the service API. Phone numbers are normalized here,
 * before the domain action runs, so `whatsapp` and `mother_phone` are persisted (and matched
 * against existing leads) in one canonical format regardless of how the chatbot formatted
 * them. Ability and the service-account gate are enforced by route middleware.
 */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['whatsapp', 'mother_phone'] as $field) {
            $value = $this->input($field);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            // An un-normalizable number is left raw so rule validation (or the domain action)
            // reports it, rather than surfacing a 500 from here.
            try {
                $normalized[$field] = normalize_phone_number(convert_eastern_arabic_to_arabic($value));
            } catch (\InvalidArgumentException) {
                // Leave the original value in place.
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'whatsapp' => ['required', 'string', 'min:8', 'max:16'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'guardian_name' => ['required', 'string'],
            'student_name' => ['required', 'string'],
            'source' => ['required', 'string'],
            'affiliate_code' => ['nullable', 'string'],
            'mother_phone' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ];
    }
}
