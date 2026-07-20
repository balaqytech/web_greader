<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ValidPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation shape for bot-contact creation via the service API. The whatsapp number is
 * normalized here so uniqueness is enforced against one canonical format. Ability and the
 * service-account gate are enforced by route middleware.
 */
class StoreBotContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('whatsapp');

        if (is_string($value) && trim($value) !== '') {
            try {
                $this->merge([
                    'whatsapp' => normalize_phone_number(convert_eastern_arabic_to_arabic($value)),
                ]);
            } catch (\InvalidArgumentException) {
                // Leave the original value in place for rule validation to report.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string'],
            'sender_name' => ['nullable', 'string'],
            'whatsapp' => ['required', 'string', 'max:16', new ValidPhoneNumber, 'unique:bot_contacts,whatsapp'],
            'conversation_summary' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'additional_data' => ['nullable', 'array'],
        ];
    }
}
