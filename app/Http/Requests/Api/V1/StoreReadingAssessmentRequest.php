<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation shape for creating a reading-assessment submission via the service API. The
 * whatsapp number is normalized by the domain action; ability and the service-account gate are
 * enforced by route middleware.
 */
class StoreReadingAssessmentRequest extends FormRequest
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
            'student_name' => ['required', 'string'],
            'age' => ['required', 'integer', 'min:4', 'max:13'],
            'grade_level' => ['required', 'string'],
            'guardian_name' => ['required', 'string'],
            'whatsapp' => ['required', 'string'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'source' => ['required', Rule::enum(Source::class)],
            'additional_info' => ['nullable', 'array'],
        ];
    }
}
